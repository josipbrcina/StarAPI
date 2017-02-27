<?php

namespace App\Services;

use App\GenericModel;
use App\Helpers\InputHandler;
use App\Helpers\WorkDays;
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
     */
    public function aggregateForTimeRange(Profile $profile, $unixStart, $unixEnd)
    {
        // Get all profile projects
        GenericModel::setCollection('tasks');
        $profileTasks = GenericModel::where('owner', '=', $profile->id)->get();

        $estimatedHours = 0;
        $hoursDelivered = 0;
        $totalPayoutInternal = 0;
        $realPayoutInternal = 0;
        $totalPayoutExternal = 0;
        $realPayoutExternal = 0;
        $xpDiff = 0;
        $numberOfDays = (int)abs($unixEnd - $unixStart) / (24 * 60 * 60);

        $loadedProjects = [];

        // Let's aggregate task data
        foreach ($profileTasks as $task) {
            // Adjust values for profile we're looking at
            $mappedValues = $this->getTaskValuesForProfile($profile, $task);
            foreach ($mappedValues as $key => $value) {
                $task->{$key} = $value;
            }

            // Check if tasks is in selected time range and delivered
            $estimatedHours += (float)$task->estimatedHours;
            $deliveredTask = false;
            $taskInTimeRange = false;
            $unixStartDate = Carbon::createFromFormat('U', InputHandler::getUnixTimestamp($unixStart))->format('Y-m-d');
            $unixEndDate = Carbon::createFromFormat('U', InputHandler::getUnixTimestamp($unixEnd))->format('Y-m-d');
            foreach ($task->work as $userId => $workStats) {
                if ($userId === $profile->id) {
                    $assignedDate =
                        Carbon::createFromFormat('U', InputHandler::getUnixTimestamp($workStats['timeAssigned']))
                            ->format('Y-m-d');
                    $lastActivityDate =
                        Carbon::createFromFormat('U', InputHandler::getUnixTimestamp($workStats['workTrackTimestamp']))
                            ->format('Y-m-d');
                    // Get task if it's not finished and timeAssigned is within time range
                    if ($task->passed_qa === false && $assignedDate <= $unixEndDate
                        && $assignedDate >= $unixStartDate
                    ) {
                        $taskInTimeRange = true;
                        break;
                    }
                    // Get task if finished and if delivery time (passed_qa) is within time range
                    if ($task->passed_qa === true && $lastActivityDate <= $unixEndDate
                        && $lastActivityDate >= $unixStartDate
                    ) {
                        $taskInTimeRange = true;
                        $deliveredTask = true;
                        break;
                    }
                }
            }

            // Skip task if not in time range
            if (!$taskInTimeRange) {
                continue;
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

            if ($deliveredTask === true) {
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

        $out = [
            'estimatedHours' => $estimatedHours,
            'hoursDelivered' => $hoursDelivered,
            'totalPayoutExternal' => $totalPayoutExternal,
            'realPayoutExternal' => $realPayoutExternal,
            'totalPayoutInternal' => $totalPayoutInternal,
            'realPayoutInternal' => $realPayoutInternal,
            'totalPayoutCombined' => $totalPayoutCombined,
            'realPayoutCombined' => $realPayoutCombined,
            'xpDiff' => $xpDiff,
            'xpTotal' => $profile->xp,
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
     * Calculates payout, xp award and estimate for specific $profile <-> $task relation
     *
     * @param Profile $profile
     * @param GenericModel $task
     * @return array
     */
    public function getTaskValuesForProfile(Profile $profile, GenericModel $task)
    {
        $xp = (float)$profile->xp;

        $taskComplexity = max((int)$task->complexity, 1);

        $estimatedHours = (float)$task->estimatedHours * 1000 / min($xp, 1000);

        // Award xp based on complexity, task priority and duration coefficient
        $taskPriorityCoefficient = null;
        if (isset($task->priorityCoefficient)) {
            $taskPriorityCoefficient = $task->priorityCoefficient;
        } else {
            $taskPriorityCoefficient = $this->taskPriorityCoefficient($profile, $task);
        }

        $xpAward = $xp <= 200 ? $taskComplexity * $estimatedHours * 10 / $xp *
            $taskPriorityCoefficient * $this->getDurationCoefficient($profile, $estimatedHours) :
            $taskPriorityCoefficient * $this->getDurationCoefficient($profile, $estimatedHours);

        $hourlyRate = Config::get('sharedSettings.internalConfiguration.hourlyRate');

        $out = [];
        $out['xp'] = $xpAward;
        $out['payout'] = InputHandler::getFloat($hourlyRate) * $task->estimatedHours;
        if (isset($task->noPayout) && $task->noPayout === true) {
            $out['payout'] = 0;
        }
        $out['estimatedHours'] = $estimatedHours;

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

        //get all projects that user is a member of
        $preSetCollection = GenericModel::getCollection();
        GenericModel::setCollection('projects');
        $taskOwnerProjects = GenericModel::whereIn('members', [$taskOwner->id])
            ->get();

        GenericModel::setCollection('tasks');

        $unassignedTasksPriority = [];

        //get all unassigned tasks from projects that user is a member of, and make list of tasks priority
        foreach ($taskOwnerProjects as $project) {
            $projectTasks = GenericModel::where('project_id', '=', $project->id)
                ->get();
            foreach ($projectTasks as $projectTask) {
                if (empty($projectTask->owner) && !in_array($projectTask->priority, $unassignedTasksPriority)) {
                    $unassignedTasksPriority[$projectTask->id] = $projectTask->priority;
                }
            }
        }

        //check task priority and compare with list of unassigned tasks priority and set task priority coefficient
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
     * @param Profile $taskOwner
     * @param $estimatedHours
     * @return float|int
     */
    private function getDurationCoefficient(Profile $taskOwner, $estimatedHours)
    {
        $profileCoefficient = 0.9;
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
            return ((int)$estimatedHours / 10) * $profileCoefficient;
        }

        return $profileCoefficient;
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
        $monthWorkDays = WorkDays::getWorkDays();

        //default values if user is not employee so roleMinimum is 0
        if ($aggregated['roleMinimum'] === 0) {
            $aggregated['earnedPercentage'] = sprintf("%d%%", 0);
            $aggregated['expectedPercentage'] = sprintf("%d%%", 0);
            $aggregated['monthPrediction'] = 0;
            $aggregated['monthPredictionPercentage'] = sprintf("%d%%", 0);

            return $aggregated;
        }

        $expectedPercentage = $aggregated['totalPayoutCombined'] === 0 ? sprintf("%d%%", 0) :
            sprintf("%d%%", ($aggregated['totalPayoutCombined'] / $aggregated['roleMinimum']) * 100);

        $earnedPercentage = $aggregated['realPayoutCombined'] === 0 ? sprintf("%d%%", 0) :
            sprintf("%d%%", ($aggregated['realPayoutCombined'] / $aggregated['roleMinimum']) * 100);

        $monthlyProjection = $aggregated['realPayoutCombined'] === 0 ? 0 :
            ($aggregated['realPayoutCombined'] / $numberOfDays) * count($monthWorkDays);

        $monthlyProjectionPercentage = $monthlyProjection === 0 ? sprintf("%d%%", 0) :
            sprintf("%d%%", ($monthlyProjection / $aggregated['roleMinimum']) * 100);

        $aggregated['earnedPercentage'] = $earnedPercentage;
        $aggregated['expectedPercentage'] = $expectedPercentage;
        $aggregated['monthPrediction'] = $this->roundFloat($monthlyProjection, 2, 10);
        $aggregated['monthPredictionPercentage'] = $monthlyProjectionPercentage;

        return $aggregated;
    }
}
