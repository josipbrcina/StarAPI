<?php

namespace Tests\Services;

use App\Exceptions\UserInputException;
use App\GenericModel;
use App\Helpers\InputHandler;
use App\Helpers\WorkDays;
use App\Profile;
use App\Services\ProfilePerformance;
use Carbon\Carbon;
use Tests\Collections\ProfileRelated;
use Tests\Collections\ProjectRelated;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class ProfilePerformanceTest extends TestCase
{
    use ProjectRelated, ProfileRelated;

    private $projectOwner = null;

    public function setUp()
    {
        parent::setUp();

        $this->setTaskOwner(Profile::create([
            'skills' => ['PHP']
        ]));
        $this->profile->xp = 200;
        $this->profile->employeeRole = 'Apprentice';
        $this->profile->save();

        $this->projectOwner = new Profile();

        $this->projectOwner->save();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->profile->delete();
        $this->projectOwner->delete();
    }

    /**
     * Test empty task history
     */
    public function testCheckPerformanceForEmptyHistory()
    {
        $task = $this->getAssignedTask();

        $pp = new ProfilePerformance();

        $out = $pp->perTask($task);

        $this->assertEquals(
            [
                $this->profile->id => [
                    'workSeconds' => 0,
                    'pauseSeconds' => 0,
                    'qaSeconds' => 0,
                    'qaProgressSeconds' => 0,
                    'qaProgressTotalSeconds' => 0,
                    'totalNumberFailedQa' => 0,
                    'blockedSeconds' => 0,
                    'workTrackTimestamp' => $task->work[$this->profile->id]['workTrackTimestamp'],
                    'taskLastOwner' => true,
                    'taskCompleted' => false,
                ]
            ],
            $out
        );
    }

    /**
     * Test task just got assigned
     */
    public function testCheckPerformanceForTaskAssigned()
    {
        // Assigned 5 minutes ago
        $minutesWorking = 5;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getTaskWithJustAssignedHistory($assignedAgo);

        $pp = new ProfilePerformance();

        $out = $pp->perTask($task);

        $this->assertCount(1, $out);

        $this->assertArrayHasKey($this->profile->id, $out);

        $profilePerformanceArray = $out[$this->profile->id];

        $this->assertArrayHasKey('taskCompleted', $profilePerformanceArray);
        $this->assertArrayHasKey('workSeconds', $profilePerformanceArray);
        $this->assertArrayHasKey('pauseSeconds', $profilePerformanceArray);
        $this->assertArrayHasKey('qaSeconds', $profilePerformanceArray);
        $this->assertArrayHasKey('qaProgressSeconds', $profilePerformanceArray);
        $this->assertArrayHasKey('qaProgressTotalSeconds', $profilePerformanceArray);
        $this->assertArrayHasKey('blockedSeconds', $profilePerformanceArray);
        $this->assertArrayHasKey('workTrackTimestamp', $profilePerformanceArray);


        $this->assertEquals(false, $profilePerformanceArray['taskCompleted']);
        $this->assertEquals($minutesWorking * 60, $profilePerformanceArray['workSeconds']);
        $this->assertEquals(0, $profilePerformanceArray['qaSeconds']);
        $this->assertEquals(0, $profilePerformanceArray['pauseSeconds']);
    }

    /**
     * Test profile performance XP difference output for 5 days with XP record
     */
    public function testProfilePerformanceForTimeRangeXpDiff()
    {
        $profileXpRecord = $this->getXpRecord();
        $workDays = WorkDays::getWorkDays(Carbon::now()->format('U'));
        foreach ($workDays as $day) {
            $this->addXpRecord($profileXpRecord, \DateTime::createFromFormat('Y-m-d', $day)->format('U'));
        }

        $pp = new ProfilePerformance();
        //Test XP diff within time range with XP records
        $out = $pp->aggregateForTimeRange(
            $this->profile,
            (int)\DateTime::createFromFormat('Y-m-d', $workDays[0])->format('U'),
            (int)\DateTime::createFromFormat('Y-m-d', $workDays[4])->format('U')
        );

        $this->assertEquals(5, $out['xpDiff']);
    }

    /**
     * Test Test profile performance XP difference output for 10 days with XP record
     */
    public function testProfilePerformanceForTimeRangeXpDifference()
    {
        $profileXpRecord = $this->getXpRecord();
        $workDays = WorkDays::getWorkDays(Carbon::now()->format('U'));
        foreach ($workDays as $day) {
            $this->addXpRecord($profileXpRecord, \DateTime::createFromFormat('Y-m-d', $day)->format('U'));
        }

        $pp = new ProfilePerformance();
        //Test XP diff within time range with XP records
        $out = $pp->aggregateForTimeRange(
            $this->profile,
            (int)\DateTime::createFromFormat('Y-m-d', $workDays[6])->format('U'),
            (int)\DateTime::createFromFormat('Y-m-d', $workDays[15])->format('U')
        );

        $this->assertEquals(10, $out['xpDiff']);
    }

    /**
     * Test Test profile performance XP difference for time range where there are no XP records
     */
    public function testProfilePerformanceForTimeRangeXpDifferenceWithNoXp()
    {
        $profileXpRecord = $this->getXpRecord();
        $workDays = WorkDays::getWorkDays(Carbon::now()->format('U'));
        foreach ($workDays as $day) {
            $this->addXpRecord($profileXpRecord, \DateTime::createFromFormat('Y-m-d', $day)->format('U'));
        }

        $pp = new ProfilePerformance();
        //Test XP diff for time range where there are no XP records
        $startTime = (int)(new \DateTime())->modify('+50 days')->format('U');
        $endTime = (int)(new \DateTime())->modify('+55 days')->format('U');
        $out = $pp->aggregateForTimeRange($this->profile, $startTime, $endTime);

        $this->assertEquals(0, $out['xpDiff']);
    }

    /**
     * Test Test profile performance XP difference for time range of 3 days (2 days are without XP records)
     */
    public function testProfilePerformanceForTimeRangeFiveDaysXpDifference()
    {
        $profileXpRecord = $this->getXpRecord();
        $workDays = WorkDays::getWorkDays(Carbon::now()->format('U'));
        foreach ($workDays as $day) {
            $this->addXpRecord($profileXpRecord, \DateTime::createFromFormat('Y-m-d', $day)->format('U'));
        }

        $pp = new ProfilePerformance();
        //Test XP diff when first 2 days of check there are no xp records and 3rd day there is one record
        $twoDaysBeforeFirstWorkDay = (int)(new \DateTime(reset($workDays)))->modify('-2 days')->format('U');
        $firstWorkDay = (int)\DateTime::createFromFormat('Y-m-d', reset($workDays))->format('U');

        $out = $pp->aggregateForTimeRange($this->profile, $twoDaysBeforeFirstWorkDay, $firstWorkDay);

        $this->assertEquals(1, $out['xpDiff']);
    }

    /**
     * Test profile performance for six days time range, with some tasks within time range and out of time range
     */
    public function testProfilePerformanceTaskCalculationDeliveryForTimeRangeSixDays()
    {
        GenericModel::setCollection('hourly-rates');
        GenericModel::truncate();
        GenericModel::create([
            'hourlyRates' => [
                'PHP' => 500,
                'React' => 500,
                'DevOps' => 500,
                'Node' => 500,
                'Planning' => 500,
                'Management' => 500
            ]
        ]);

        $project = $this->getNewProject();
        $project->save();

        $workDays = WorkDays::getWorkDays(Carbon::now()->format('U'));
        $tasks = [];
        $counter = 1;
        $skills = ['PHP'];
        foreach ($workDays as $day) {
            $unixDay = \DateTime::createFromFormat('Y-m-d', $day)->format('U');
            $task = $this->getAssignedTask($unixDay);
            $task->skillset = $skills;
            $task->estimatedHours = 1;
            $task->project_id = $project->id;
            if ($counter % 2 === 0) {
                $task->passed_qa = true;
                $task->timeFinished = (int)$unixDay;
                $work = $task->work;
                $work[$this->profile->id]['qa_total_time'] = 1800;
                $task->work = $work;
            }
            $task->save();
            $tasks[$unixDay] = $task;
            $counter++;
        }

        $workDaysUnixTimestamps = array_keys($tasks);

        $pp = new ProfilePerformance();
        $out = $pp->aggregateForTimeRange($this->profile, $workDaysUnixTimestamps[0], $workDaysUnixTimestamps[5]);

        $this->assertEquals(30, $out['estimatedHours']);
        $this->assertEquals(15, $out['hoursDelivered']);
        $this->assertEquals(3000, $out['totalPayoutExternal']);
        $this->assertEquals(1500, $out['realPayoutExternal']);
        $this->assertEquals(0, $out['totalPayoutInternal']);
        $this->assertEquals(0, $out['totalPayoutInternal']);
        $this->assertEquals(0, $out['realPayoutInternal']);
        $this->assertEquals(1.5, $out['hoursDoingQA']);
    }

    /**
     * Test profile performance aggregate for time range wrong input format
     */
    public function testProfilePerformanceAggregateForTimeRangeWrongInput()
    {
        // String timestamp
        $unixNow = Carbon::now()->format('U');
        // Integer timestamp
        $unix2DaysAgo = (int)Carbon::now()->subDays(2)->format('U');

        $pp = new ProfilePerformance();

        $this->setExpectedException(
            UserInputException::class,
            'Invalid time range input. Must be type of integer',
            400
        );
        $out = $pp->aggregateForTimeRange($this->profile, $unix2DaysAgo, $unixNow);
        $this->assertEquals($out, $this->getExpectedException());

        // Integer timestamp
        $unixNowInteger = (int)Carbon::now()->format('U');
        // String timestamp
        $unix2DaysAgoString = Carbon::now()->subDays(2)->format('U');

        $this->setExpectedException(
            UserInputException::class,
            'Invalid time range input. Must be type of integer',
            400
        );
        $out = $pp->aggregateForTimeRange($this->profile, $unix2DaysAgoString, $unixNowInteger);
        $this->assertEquals($out, $this->getExpectedException());
    }

    /**
     * Test profile performance task priority coefficient = 1.0
     */
    public function testProfilePerformanceTaskPriorityCoefficientNoDeduction()
    {
        GenericModel::setCollection('tasks');
        GenericModel::truncate();
        // Get new project
        $project = $this->getNewProject();
        $members = [$this->profile->id];
        $project->members = $members;
        $project->save();

        // Create skillsets for tasks
        $skillSetMatch = [
            'PHP',
            'Planning',
            'React'
        ];

        $skillSet = [
            'React',
            'DevOps'
        ];

        // Create some tasks and set skillset
        $lowPriorityTask = $this->getNewTask();
        $lowPriorityTask->project_id = $project->id;
        $lowPriorityTask->priority = 'Low';
        $lowPriorityTask->skillset = $skillSetMatch;
        $lowPriorityTask->save();

        $mediumPriorityTask = $this->getNewTask();
        $mediumPriorityTask->project_id = $project->id;
        $mediumPriorityTask->priority = 'Medium';
        $mediumPriorityTask->skillset = $skillSet;
        $mediumPriorityTask->save();

        $highPriorityTask = $this->getNewTask();
        $highPriorityTask->project_id = $project->id;
        $highPriorityTask->priority = 'High';
        $highPriorityTask->skillset = $skillSet;
        $highPriorityTask->save();

        // Test task priority coefficient
        $pp = new ProfilePerformance();
        $out = $pp->taskPriorityCoefficient($this->profile, $lowPriorityTask);
        $this->assertEquals(1.0, $out);
    }

    /**
     * Test profile performance task priority coefficient = 0.5
     */
    public function testProfilePerformanceTaskPriorityCoefficientMediumDeduction()
    {
        GenericModel::setCollection('tasks');
        GenericModel::truncate();
        // Get new project
        $project = $this->getNewProject();
        $members = [$this->profile->id];
        $project->members = $members;
        $project->save();

        // Create skillsets for tasks
        $skillSetMatch = [
            'PHP',
            'Planning',
            'React'
        ];

        $skillSet = [
            'React',
            'DevOps'
        ];

        // Create some tasks and set skillset
        $lowPriorityTask = $this->getNewTask();
        $lowPriorityTask->project_id = $project->id;
        $lowPriorityTask->priority = 'Low';
        $lowPriorityTask->skillset = $skillSetMatch;
        $lowPriorityTask->save();

        $mediumPriorityTask = $this->getNewTask();
        $mediumPriorityTask->project_id = $project->id;
        $mediumPriorityTask->priority = 'Medium';
        $mediumPriorityTask->skillset = $skillSetMatch;
        $mediumPriorityTask->save();

        $highPriorityTask = $this->getNewTask();
        $highPriorityTask->project_id = $project->id;
        $highPriorityTask->priority = 'High';
        $highPriorityTask->skillset = $skillSet;
        $highPriorityTask->save();

        // Test task priority coefficient
        $pp = new ProfilePerformance();
        $out = $pp->taskPriorityCoefficient($this->profile, $lowPriorityTask);
        $this->assertEquals(0.5, $out);
    }

    /**
     * Test profile performance task priority coefficient = 0.8
     */
    public function testProfilePerformanceTaskPriorityCoefficientHighDeduction()
    {
        GenericModel::setCollection('tasks');
        GenericModel::truncate();
        // Get new project
        $project = $this->getNewProject();
        $members = [$this->profile->id];
        $project->members = $members;
        $project->save();

        // Create skillsets for tasks
        $skillSetMatch = [
            'PHP',
            'Planning',
            'React'
        ];

        $skillSet = [
            'React',
            'DevOps'
        ];

        // Create some tasks and set skillset
        $lowPriorityTask = $this->getNewTask();
        $lowPriorityTask->project_id = $project->id;
        $lowPriorityTask->priority = 'Low';
        $lowPriorityTask->skillset = $skillSet;
        $lowPriorityTask->save();

        $mediumPriorityTask = $this->getNewTask();
        $mediumPriorityTask->project_id = $project->id;
        $mediumPriorityTask->priority = 'Medium';
        $mediumPriorityTask->skillset = $skillSet;
        $mediumPriorityTask->save();

        $highPriorityTask = $this->getNewTask();
        $highPriorityTask->project_id = $project->id;
        $highPriorityTask->priority = 'High';
        $highPriorityTask->skillset = $skillSetMatch;
        $highPriorityTask->save();

        // Test task priority coefficient
        $pp = new ProfilePerformance();
        $out = $pp->taskPriorityCoefficient($this->profile, $mediumPriorityTask);
        $this->assertEquals(0.8, $out);
    }

    /**
     * Test profile performance task payout with one skill on task
     */
    public function testProfilePerformanceTaskPayoutOneSkill()
    {
        GenericModel::setCollection('hourly-rates');
        GenericModel::truncate();
        $hourlyRatesPerSkill = GenericModel::create([
            'hourlyRates' => [
                'PHP' => 500,
                'React' => 500,
                'DevOps' => 500,
                'Node' => 500,
                'Planning' => 500,
                'Management' => 500
            ]
        ]);
        $skills = ['PHP'];
        $task = $this->getNewTask();
        $task->skillset = $skills;
        $task->estimatedHours = 2;
        $task->save();

        $hourlyRate = 0;
        $skillCompare = array_intersect_key(array_flip($task->skillset), $hourlyRatesPerSkill->hourlyRates);
        foreach ($skillCompare as $key => $value) {
            $hourlyRate += $hourlyRatesPerSkill->hourlyRates[$key];
        }
        // Calculate average hourly rate per skill if task has got more then one skill
        if (count($skillCompare) > 0) {
            $hourlyRate = $hourlyRate / count($skillCompare);
        }

        $pp = new ProfilePerformance();
        $out = $pp->getTaskValuesForProfile($this->profile, $task);
        $this->assertEquals($hourlyRate * $task->estimatedHours, $out['payout']);
    }

    /**
     * Test profile performance task payout with many skills
     */
    public function testProfilePerformanceTaskPayoutOneSkillManySkills()
    {
        GenericModel::setCollection('hourly-rates');
        GenericModel::truncate();
        $hourlyRatesPerSkill = GenericModel::create([
            'hourlyRates' => [
                'PHP' => 240,
                'React' => 380,
                'DevOps' => 500,
                'Node' => 500,
                'Planning' => 500,
                'Management' => 500
            ]
        ]);
        $skills = ['PHP', 'Node', 'React'];
        $task = $this->getNewTask();
        $task->skillset = $skills;
        $task->estimatedHours = 3;
        $task->save();

        $hourlyRate = 0;
        $skillCompare = array_intersect_key(array_flip($task->skillset), $hourlyRatesPerSkill->hourlyRates);
        foreach ($skillCompare as $key => $value) {
            $hourlyRate += $hourlyRatesPerSkill->hourlyRates[$key];
        }
        // Calculate average hourly rate per skill if task has got more then one skill
        if (count($skillCompare) > 0) {
            $hourlyRate = $hourlyRate / count($skillCompare);
        }

        $pp = new ProfilePerformance();
        $out = $pp->getTaskValuesForProfile($this->profile, $task);
        $this->assertEquals($hourlyRate * $task->estimatedHours, $out['payout']);
    }

    /**
     * Test profile performance task payout for task without skills
     */
    public function testProfilePerformanceTaskPayoutWithoutSkills()
    {
        GenericModel::setCollection('hourly-rates');
        GenericModel::truncate();
        $hourlyRatesPerSkill = GenericModel::create([
            'hourlyRates' => [
                'PHP' => 240,
                'React' => 380,
                'DevOps' => 500,
                'Node' => 500,
                'Planning' => 500,
                'Management' => 500
            ]
        ]);
        $skills = [];
        $task = $this->getNewTask();
        $task->skillset = $skills;
        $task->estimatedHours = 3;
        $task->save();

        $hourlyRate = 0;
        $skillCompare = array_intersect_key(array_flip($task->skillset), $hourlyRatesPerSkill->hourlyRates);
        foreach ($skillCompare as $key => $value) {
            $hourlyRate += $hourlyRatesPerSkill->hourlyRates[$key];
        }
        // Calculate average hourly rate per skill if task has got more then one skill
        if (count($skillCompare) > 0) {
            $hourlyRate = $hourlyRate / count($skillCompare);
        }

        $pp = new ProfilePerformance();
        $out = $pp->getTaskValuesForProfile($this->profile, $task);
        $this->assertEquals($hourlyRate * $task->estimatedHours, $out['payout']);
    }

    /**
     * Test profile performance task payout for task that has set noPayout = true
     */
    public function testProfilePerformanceTaskPayoutWithNoPayout()
    {
        $task = $this->getNewTask();
        $task->estimatedHours = 3;
        $task->noPayout = true;
        $task->save();

        $pp = new ProfilePerformance();
        $out = $pp->getTaskValuesForProfile($this->profile, $task);
        $this->assertEquals(0, $out['payout']);
    }

    /**
     * Test minimum earning for current month with 10 days vacation
     */
    public function testMinimumForCurrentMonthWithVacation()
    {
        GenericModel::setCollection('vacations');
        GenericModel::truncate();
        $workDays = WorkDays::getWorkDays(Carbon::now()->format('U'));
        $vacation = new GenericModel([
            'records' => [
                [
                    'dateFrom' => Carbon::createFromFormat('Y-m-d', $workDays[5])->format('U'),
                    'dateTo' => Carbon::createFromFormat('Y-m-d', $workDays[14])->format('U'),
                    'recordTimestamp' => (int) Carbon::now()->format('U')
                ]
            ]
        ]);
        $vacation->_id = $this->profile->id;
        $vacation->save();

        $employeeConfig = Config::get('sharedSettings.internalConfiguration.employees.roles');

        $role = $this->profile->employeeRole;
        $baseMinimum = $employeeConfig[$role]['minimumEarnings'];

        $calculatedMinimum = (10 / count($workDays)) * $baseMinimum;

        $pp = new ProfilePerformance();
        $out = $pp->aggregateForTimeRange(
            $this->profile,
            (int) Carbon::now()->format('U'),
            (int) Carbon::now()->addDays(5)->format('U')
        );
        $this->assertEquals($calculatedMinimum, $out['roleMinimum']);
    }

    /**
     * Test minimum earning for last month with vacation period last 5 days in previous month until now
     */
    public function testMinimumForLastMonthWithVacationAcrossTwoMonths()
    {
        GenericModel::setCollection('vacations');
        GenericModel::truncate();
        $workDays = WorkDays::getWorkDays(Carbon::now()->subMonth(1)->format('U'));
        $fifthWorkDayUntilLast = count($workDays) - 5;
        $vacation = new GenericModel([
            'records' => [
                [
                    'dateFrom' => Carbon::createFromFormat('Y-m-d', $workDays[$fifthWorkDayUntilLast])
                        ->format('U'),
                    'dateTo' => Carbon::now()->format('U'),
                    'recordTimestamp' => (int) Carbon::now()->format('U')
                ]
            ]
        ]);
        $vacation->_id = $this->profile->id;
        $vacation->save();

        $employeeConfig = Config::get('sharedSettings.internalConfiguration.employees.roles');

        $role = $this->profile->employeeRole;
        $baseMinimum = $employeeConfig[$role]['minimumEarnings'];

        $calculatedMinimum = ($fifthWorkDayUntilLast / count($workDays)) * $baseMinimum;
        $calculatedMinimum = InputHandler::roundFloat($calculatedMinimum, 2, 10);
        $pp = new ProfilePerformance();
        $out = $pp->aggregateForTimeRange(
            $this->profile,
            (int) Carbon::now()->subMonth(1)->format('U'),
            (int) Carbon::now()->format('U')
        );
        $this->assertEquals($calculatedMinimum, $out['roleMinimum']);
    }
}
