<?php

namespace App\Services;

use App\Exceptions\UserInputException;
use App\GenericModel;
use App\Helpers\InputHandler;
use App\Helpers\ProfileOverall;
use App\Profile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Class ProfilePerformance
 * @package App\Services
 */
class ProfilePerformance
{
    /**
     * @param Profile $profile
     * @param $unixStart
     * @param $unixEnd
     * @return array
     * @throws UserInputException
     */
    public function aggregateForTimeRange(Profile $profile, $unixStart, $unixEnd)
    {
        // Make sure that unixStart and unixEnd are integer format
        if (!is_int($unixStart) || !is_int($unixEnd)) {
            throw new UserInputException('Invalid time range input. Must be type of integer');
        }

        // Get all profile tasks
        GenericModel::setCollection('tasks');
        $profileTasksUnfinished = GenericModel::where('owner', '=', $profile->id)
            ->where('passed_qa', '=', false)
            ->where('timeAssigned', '>=', $unixStart)
            ->where('timeAssigned', '<=', $unixEnd)
            ->get();

        $profileTasksFinished = GenericModel::where('owner', '=', $profile->id)
            ->where('passed_qa', '=', true)
            ->where('timeFinished', '>=', $unixStart)
            ->where('timeFinished', '<=', $unixEnd)
            ->get();

        $profileTasks = $profileTasksUnfinished->merge($profileTasksFinished);

        $estimatedHours = 0;
        $hoursDelivered = 0;
        $totalWorkSeconds = 0;
        $totalNumberFailedQa = 0;
        $totalPayoutInternal = 0;
        $realPayoutInternal = 0;
        $totalPayoutExternal = 0;
        $realPayoutExternal = 0;
        $xpDiff = 0;
        $timeDoingQa = 0;
        $numberOfDays = (int)abs($unixEnd - $unixStart) / (24 * 60 * 60);

        $loadedProjects = [];

        // Let's aggregate task data
        foreach ($profileTasks as $task) {
            // Adjust values for profile we're looking at
            $mappedValues = $this->getTaskValuesForProfile($profile, $task);
            foreach ($mappedValues as $key => $value) {
                $task->{$key} = $value;
            }

            if (array_key_exists($profile->id, $task->work)) {
                foreach ($task->work as $userId => $workStats) {
                    if ($userId === $profile->id) {
                        $estimatedHours += (float)$task->estimatedHours;
                        $timeDoingQa += $workStats['qa_total_time'];
                        $totalWorkSeconds += $workStats['worked'];
                        $totalNumberFailedQa += $workStats['numberFailedQa'];
                    } else {
                        $timeDoingQa += $workStats['qa_total_time'];
                    }
                }
            } else {
                $estimatedHours += (float)$task->estimatedHours; // For old tasks without work field
            }

            // Get the project if not loaded already
            if (!array_key_exists($task->project_id, $loadedProjects)) {
                GenericModel::setCollection('projects');
                $loadedProjects[$task->project_id] = GenericModel::find($task->project_id);
            }

            $project = $loadedProjects[$task->project_id];
            $isInternalProject = $project->isInternal;

            if ($isInternalProject) {
                $totalPayoutInternal += $task->payout;
            } else {
                $totalPayoutExternal += $task->payout;
            }

            if ($task->passed_qa) {
                $hoursDelivered += (int)$task->estimatedHours;

                if ($isInternalProject) {
                    $realPayoutInternal += $task->payout;
                } else {
                    $realPayoutExternal += $task->payout;
                }
            }
        }

        // Let's see the XP diff within time range
        if ($profile->xp_id) {
            GenericModel::setCollection('xp');
            $unixStartDate = InputHandler::getUnixTimestamp($unixStart);
            $unixEndDate = InputHandler::getUnixTimestamp($unixEnd);
            $xpRecord = GenericModel::find($profile->xp_id);
            if ($xpRecord) {
                foreach ($xpRecord->records as $record) {
                    $recordTimestamp = InputHandler::getUnixTimestamp($record['timestamp']);
                    if ($recordTimestamp >= $unixStartDate && $recordTimestamp <= $unixEndDate) {
                        $xpDiff += $record['xp'];
                    }
                }
            }
        }

        // Sum up totals
        $totalPayoutCombined = $totalPayoutExternal + $totalPayoutInternal;
        $realPayoutCombined = $realPayoutExternal + $realPayoutInternal;
        $timeDoingQaHours = $this->roundFloat(($timeDoingQa / 60 / 60), 2, 5);
        $totalWorkHours = $this->roundFloat($totalWorkSeconds / 60 / 60, 2, 5);
        $qaSuccessRate = $totalNumberFailedQa > 0 ?
            sprintf("%d", $totalNumberFailedQa / count($profileTasks) * 100)
            : sprintf("%d", 100);
        $profileOverall = ProfileOverall::getProfileOverallRecord($profile);

        $out = [
            'estimatedHours' => $estimatedHours,
            'hoursDelivered' => $hoursDelivered,
            'totalWorkHours' => $totalWorkHours,
            'totalPayoutExternal' => $totalPayoutExternal,
            'realPayoutExternal' => $realPayoutExternal,
            'totalPayoutInternal' => $totalPayoutInternal,
            'realPayoutInternal' => $realPayoutInternal,
            'totalPayoutCombined' => $totalPayoutCombined,
            'realPayoutCombined' => $realPayoutCombined,
            'hoursDoingQA' => $timeDoingQaHours,
            'qaSuccessRate' => $qaSuccessRate,
            'xpDiff' => $xpDiff,
            'xpTotal' => $profile->xp,
            'OverallTotalEarned' => $profileOverall->totalEarned,
            'OverallTotalCost' => $profileOverall->totalCost,
            'OverallProfit' => $profileOverall->profit
        ];

        $out = array_merge($out, $this->calculateSalary($out, $profile));
        $out = array_merge($out, $this->calculateEarningEstimation($out, $numberOfDays));

        return $out;
    }

    /**
     * Outputs hash map with seconds spent in work, pause and qa together with flag is task completed
     *
     * @param GenericModel $task
     * @return array
     */
    public function perTask(GenericModel $task)
    {
        $task->confirmResourceOf('tasks');

        $taskWorkHistory = is_array($task->work) ? $task->work : [];

        // We'll respond with array of performance per task owner (if task got reassigned for example)
        $response = [];

        // If task was never assigned, there's no performance, respond with empty array
        if (empty($taskWorkHistory)) {
            return $response;
        }

        $userPerformance = [
            'taskCompleted' => $task->passed_qa === true ? true : false
        ];

        foreach ($taskWorkHistory as $taskOwnerId => $stats) {
            $userPerformance['workSeconds'] = $stats['worked'];
            $userPerformance['pauseSeconds'] = $stats['paused'];
            $userPerformance['qaSeconds'] = $stats['qa'];
            $userPerformance['qaProgressSeconds'] = $stats['qa_in_progress'];
            $userPerformance['qaProgressTotalSeconds'] = $stats['qa_total_time'];
            $userPerformance['totalNumberFailedQa'] = $stats['numberFailedQa'];
            $userPerformance['blockedSeconds'] = $stats['blocked'];
            $userPerformance['workTrackTimestamp'] = $stats['workTrackTimestamp'];

            // Let's just add diff based of last task state against current time if task not done yet
            if (!key_exists('timeRemoved', $stats) && $userPerformance['taskCompleted'] !== true) {
                $unixNow = (int)(new \DateTime())->format('U');
                if ($task->paused !== true
                    && $task->blocked !== true
                    && $task->submitted_for_qa !== true
                    && $task->qa_in_progress !== true
                ) {
                    $userPerformance['workSeconds'] += $unixNow - $stats['workTrackTimestamp'];
                }
                if ($task->paused) {
                    $userPerformance['pauseSeconds'] += $unixNow - $stats['workTrackTimestamp'];
                }
                if ($task->submitted_for_qa) {
                    $userPerformance['qaSeconds'] += $unixNow - $stats['workTrackTimestamp'];
                }
                if ($task->blocked) {
                    $userPerformance['blockedSeconds'] += $unixNow - $stats['workTrackTimestamp'];
                }
                if ($task->qa_in_progress) {
                    $userPerformance['qaProgressSeconds'] += $unixNow - $stats['workTrackTimestamp'];
                    $userPerformance['qaProgressTotalSeconds'] += $unixNow - $stats['workTrackTimestamp'];
                }
            }

            //set last task owner flag so we can calculate payment and XP when task is finished
            if (!key_exists('timeRemoved', $stats)) {
                $userPerformance['taskLastOwner'] = true;
            } else {
                $userPerformance['taskLastOwner'] = false;
            }

            $response[$taskOwnerId] = $userPerformance;

            //remove flag from user performance array because only one user should have it
            unset($userPerformance['taskLastOwner']);
        }

        return $response;
    }

    /**
     * Get task payout, xp award and estimate for specific $profile <-> $task relation
     *
     * @param Profile $profile
     * @param GenericModel $task
     * @return array
     */
    public function getTaskValuesForProfile(Profile $profile, GenericModel $task)
    {
        $xpAwardMultiplyBy = 2;
        $xpDeductionMultiplyBy = 15;

        $xpAward = $this->calculateXpAwardOrDeduction($profile, $task, $xpAwardMultiplyBy);
        $xpDeduction = $this->calculateXpAwardOrDeduction($profile, $task, $xpDeductionMultiplyBy);

        $out = [];
        $out['xp'] = $xpAward;
        $out['xpDeduction'] = $xpDeduction;
        $out['payout'] = $this->calculateTaskPayout($task);
        $out['estimatedHours'] = $this->calculateTaskEstimatedHours($profile, $task);

        return $out;
    }

    /**
     * Calculate task priority coefficient
     * @param Profile $taskOwner
     * @param GenericModel $task
     * @return float|int
     */
    public function taskPriorityCoefficient(Profile $taskOwner, GenericModel $task)
    {
        $taskPriorityCoefficient = 1;

        // Get all projects that user is a member of
        $preSetCollection = GenericModel::getCollection();
        GenericModel::setCollection('projects');
        $taskOwnerProjects = GenericModel::whereIn('members', [$taskOwner->id])
            ->get();

        GenericModel::setCollection('tasks');

        $unassignedTasksPriority = [];

        // Get all unassigned tasks from projects that user is a member of, and make list of tasks priority
        foreach ($taskOwnerProjects as $project) {
            $projectTasks = GenericModel::where('project_id', '=', $project->id)
                ->get();
            foreach ($projectTasks as $projectTask) {
                // Let's compare user skills with task skillset
                $compareSkills = array_intersect($taskOwner->skills, $projectTask->skillset);
                if (empty($projectTask->owner)
                    && !in_array($projectTask->priority, $unassignedTasksPriority)
                    && !empty($compareSkills)
                ) {
                    $unassignedTasksPriority[$projectTask->id] = $projectTask->priority;
                }
            }
        }

        // Check task priority and compare with list of unassigned tasks priority and set task priority coefficient
        if ($task->priority === 'Low'
            && (in_array('Medium', $unassignedTasksPriority) || in_array('High', $unassignedTasksPriority))
        ) {
            $taskPriorityCoefficient = 0.5;
        }

        if ($task->priority === 'Medium' && in_array('High', $unassignedTasksPriority)) {
            $taskPriorityCoefficient = 0.8;
        }

        GenericModel::setCollection($preSetCollection);

        return $taskPriorityCoefficient;
    }

    /**
     * Calculate task payout
     * @param GenericModel $task
     * @return float|int
     */
    private function calculateTaskPayout(GenericModel $task)
    {
        $hourlyRate = 0;

        // If task has noPayout return 0
        if (isset($task->noPayout) && $task->noPayout === true) {
            return $hourlyRate;
        }

        $preSetCollection = GenericModel::getCollection();
        GenericModel::setCollection('hourly-rates');
        $hourlyRatesPerSkill = GenericModel::first();

        if ($hourlyRatesPerSkill) {
            $skillCompare = array_intersect_key(array_flip($task->skillset), $hourlyRatesPerSkill->hourlyRates);
            foreach ($skillCompare as $key => $value) {
                $hourlyRate += $hourlyRatesPerSkill->hourlyRates[$key];
            }
            // Calculate average hourly rate per skill if task has got more then one skill
            if (count($skillCompare) > 0) {
                $hourlyRate = $hourlyRate / count($skillCompare);
            }
        }

        GenericModel::setCollection($preSetCollection);

        return InputHandler::getFloat($hourlyRate) * $task->estimatedHours;
    }

    /**
     * @param Profile $taskOwner
     * @param $estimatedHours
     * @return float|int
     */
    private function getDurationCoefficient(Profile $taskOwner, $estimatedHours, $multiplyBy = null)
    {
        $profileCoefficient = 0.9;
        if ($multiplyBy === null) {
            $multiplyBy = 1;
        }
        if ((float)$taskOwner->xp > 200 && (float)$taskOwner->xp <= 400) {
            $profileCoefficient = 0.8;
        } elseif ((float)$taskOwner->xp > 400 && (float)$taskOwner->xp <= 600) {
            $profileCoefficient = 0.6;
        } elseif ((float)$taskOwner->xp > 600 && (float)$taskOwner->xp <= 800) {
            $profileCoefficient = 0.4;
        } elseif ((float)$taskOwner->xp > 800 && (float)$taskOwner->xp <= 1000) {
            $profileCoefficient = 0.2;
        } elseif ((float)$taskOwner->xp > 1000) {
            $profileCoefficient = 0.1;
        }

        if ((int)$estimatedHours < 10) {
            return ((int)$estimatedHours / 10) * ($profileCoefficient * $multiplyBy);
        }

        return $profileCoefficient * $multiplyBy;
    }

    /**
     * Calculate Xp award or deduction for specific $profile <-> $task relation
     * @param Profile $profile
     * @param GenericModel $task
     * @param $multiplyBy
     * @return float|int|mixed
     */
    private function calculateXpAwardOrDeduction(Profile $profile, GenericModel $task, $multiplyBy = null)
    {
        $xp = (float)$profile->xp;

        $taskComplexity = max((int)$task->complexity, 1);

        $estimatedHours = $this->calculateTaskEstimatedHours($profile, $task);

        if ($multiplyBy === null) {
            $multiplyBy = 1;
        }

        // Calculate xp award/deduction based on complexity, task priority and duration coefficient
        $taskPriorityCoefficient = null;
        if (isset($task->priorityCoefficient)) {
            $taskPriorityCoefficient = $task->priorityCoefficient;
        } else {
            $taskPriorityCoefficient = $this->taskPriorityCoefficient($profile, $task);
        }

        $calculatedXp = $xp <= 200 ? $taskComplexity * $estimatedHours * 10 / $xp *
            $taskPriorityCoefficient * $this->getDurationCoefficient($profile, $estimatedHours, $multiplyBy) :
            $taskPriorityCoefficient * $this->getDurationCoefficient($profile, $estimatedHours, $multiplyBy);

        return $calculatedXp;
    }

    /**
     * Calculate task estimated hours for specific $profile <-> $task relation
     * @param Profile $profile
     * @param GenericModel $task
     * @return float
     */
    private function calculateTaskEstimatedHours(Profile $profile, GenericModel $task)
    {
        $estimatedHours = (float)$task->estimatedHours * 1000 / min((float)$profile->xp, 1000);

        return $estimatedHours;
    }

    /**
     * Calculates salary based on performance in time range
     *
     * @param array $aggregated
     * @param Profile $profile
     * @return array
     */
    private function calculateSalary(array $aggregated, Profile $profile)
    {
        $employeeConfig = Config::get('sharedSettings.internalConfiguration.employees.roles');

        $role = $profile->employeeRole;

        if (!isset($employeeConfig[$role])) {
            return [
                'minimalGrossPayout' => 0,
                'realGrossPayout' => 0,
                'grossBonusPayout' => 0,
                'costXpBasedPayout' => 0,
                'employeeRole' => 'Not set',
                'roleMinimumReached' => false,
                'roleMinimum' => 0,
            ];
        }

        $minimum = $employeeConfig[$role]['minimumEarnings'];
        $coefficient = $employeeConfig[$role]['coefficient'];
        $xpEntryPoint = $employeeConfig[$role]['xpEntryPoint'];

        $realPayout = $minimum;

        // Adjust payout based on XP
        $xpInRange = (float)$profile->xp - $xpEntryPoint;

        if ($xpInRange < 0) {
            $xpInRange = 0;
        } elseif ($xpInRange > 200) {
            $xpInRange = 200;
        }

        // 50% of everything over minimum (from external projects) goes to bonus
        if ($aggregated['realPayoutExternal'] > $minimum) {
            $realPayout = $minimum + ($aggregated['realPayoutExternal'] - $minimum) / 2;
        }

        $costReal = $this->calculateSalaryCostForAmount($realPayout, $coefficient);
        $xpBasedPayout = $costReal * $coefficient * $xpInRange / 2 / 100;
        if ($aggregated['realPayoutCombined'] > $minimum) {
            $costReal += $xpBasedPayout;
        }

        $grossReal = $this->calculateSalaryGrossForAmount($costReal);

        $costGrossMinimum = $this->calculateSalaryCostForAmount($realPayout, $coefficient);
        $grossMinimum = $this->calculateSalaryGrossForAmount($costGrossMinimum);

        $aggregated['costTotal'] = $this->roundFloat($costReal, 2, 10);
        $aggregated['minimalGrossPayout'] = $this->roundFloat($grossMinimum, 2, 10);
        $aggregated['realGrossPayout'] = $this->roundFloat($grossReal, 2, 10);
        $aggregated['grossBonusPayout'] = $this->roundFloat($grossReal - $grossMinimum, 2, 10);
        $aggregated['costXpBasedPayout'] = $xpBasedPayout;
        $aggregated['employeeRole'] = $role;
        $aggregated['roleMinimumReached'] = $grossReal > $grossMinimum;
        $aggregated['roleMinimum'] = $minimum;

        return $aggregated;
    }

    /**
     * Helper to calculate salary based of earned amount
     *
     * @param $forAmount
     * @param $coefficient
     * @return float
     */
    private function calculateSalaryCostForAmount($forAmount, $coefficient)
    {
        $totalCost = $forAmount - $forAmount * $coefficient * 2;

        return $totalCost;
    }

    /**
     * Helper for salary cost conversion to gross payout
     *
     * @param $totalGross
     * @return float
     */
    private function calculateSalaryGrossForAmount($totalGross)
    {
        // 17.2% is fixed cost over gross salary in Croatia
        $gross = $totalGross / 1.172;

        return $gross;
    }

    /**
     * Helper method to round the float correctly
     *
     * @param $float
     * @param $position
     * @param $startAt
     * @return mixed
     */
    private function roundFloat($float, $position, $startAt)
    {
        if ($position < $startAt) {
            $startAt--;
            $newFloat = round($float, $startAt);
            return $this->roundFloat($newFloat, $position, $startAt);
        }

        return $float;
    }

    /**
     * Calculate earning estimation
     * @param array $aggregated
     * @param $numberOfDays
     * @return array
     */
    private function calculateEarningEstimation(array $aggregated, $numberOfDays)
    {
        // Calculate number of days
        $daysInMonth = Carbon::now()->daysInMonth;
        $daysIn3Months = Carbon::now()->diffInDays(Carbon::now()->addMonths(3));
        $daysIn6Months = Carbon::now()->diffInDays(Carbon::now()->addMonths(6));
        $daysIn12Months = Carbon::now()->diffInDays(Carbon::now()->addMonths(12));

        // Default values if user is not employee so roleMinimum is 0
        if ($aggregated['roleMinimum'] === 0) {
            $aggregated['earnedPercentage'] = sprintf("%d", 0);
            $aggregated['monthPrediction'] = 0;

            return $aggregated;
        }

        $minimumForNumberOfDays = $aggregated['roleMinimum'] / $daysInMonth * $numberOfDays;

        $earnedPercentage = sprintf("%d", $aggregated['realPayoutCombined'] / $minimumForNumberOfDays * 100);

        // Calculate earning projection
        $monthlyProjection = (float) $aggregated['realPayoutCombined'] / $numberOfDays * $daysInMonth;
        $projectionFor3Months = (float) $aggregated['realPayoutCombined'] / $numberOfDays * $daysIn3Months;
        $projectionFor6Months = (float) $aggregated['realPayoutCombined'] / $numberOfDays * $daysIn6Months;
        $projectionFor12Months = (float) $aggregated['realPayoutCombined'] / $numberOfDays * $daysIn12Months;

        // Total cost of employee per time range
        $totalEmployeeCostPerTimeRange = $aggregated['costTotal'] / $daysInMonth * $numberOfDays;

        // Calculate projection difference employee earned <--> employee cost
        $projectedDifference1Month = $monthlyProjection - $totalEmployeeCostPerTimeRange;
        $projectedDifference3Months = $projectionFor3Months -
            ($aggregated['costTotal'] / $daysInMonth * $daysIn3Months);
        $projectedDifference6Months = $projectionFor6Months -
            ($aggregated['costTotal'] / $daysInMonth * $daysIn6Months);
        $projectedDifference12Months = $projectionFor12Months -
            ($aggregated['costTotal'] / $daysInMonth * $daysIn12Months);

        // Generate output
        $aggregated['earnedPercentage'] = $earnedPercentage;
        $aggregated['monthPrediction'] = $this->roundFloat($monthlyProjection, 2, 10);
        $aggregated['totalEmployeeCostPerTimeRange'] = $this->roundFloat($totalEmployeeCostPerTimeRange, 2, 10);
        $aggregated['projectedDifference1Month'] = $this->roundFloat($projectedDifference1Month, 2, 10);
        $aggregated['projectedDifference3Months'] = $this->roundFloat($projectedDifference3Months, 2, 10);
        $aggregated['projectedDifference6Months'] = $this->roundFloat($projectedDifference6Months, 2, 10);
        $aggregated['projectedDifference12Months'] = $this->roundFloat($projectedDifference12Months, 2, 10);

        return $aggregated;
    }
}
