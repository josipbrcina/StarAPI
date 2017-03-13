<?php

namespace Tests\Services;

use App\Exceptions\UserInputException;
use App\Helpers\WorkDays;
use App\Profile;
use App\Services\ProfilePerformance;
use Carbon\Carbon;
use Tests\Collections\ProfileRelated;
use Tests\Collections\ProjectRelated;
use Tests\TestCase;

class ProfilePerformanceTest extends TestCase
{
    use ProjectRelated, ProfileRelated;

    private $projectOwner = null;

    public function setUp()
    {
        parent::setUp();

        $this->setTaskOwner(Profile::create());
        $this->profile->xp = 200;
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
        $workDays = WorkDays::getWorkDays();
        foreach ($workDays as $day) {
            $this->addXpRecord($profileXpRecord, \DateTime::createFromFormat('Y-m-d', $day)->format('U'));
        }

        $pp = new ProfilePerformance();
        //Test XP diff within time range with XP records
        $out = $pp->aggregateForTimeRange(
            $this->profile,
            (int) \DateTime::createFromFormat('Y-m-d', $workDays[0])->format('U'),
            (int) \DateTime::createFromFormat('Y-m-d', $workDays[4])->format('U')
        );

        $this->assertEquals(5, $out['xpDiff']);
    }

    /**
     * Test Test profile performance XP difference output for 10 days with XP record
     */
    public function testProfilePerformanceForTimeRangeXpDifference()
    {
        $profileXpRecord = $this->getXpRecord();
        $workDays = WorkDays::getWorkDays();
        foreach ($workDays as $day) {
            $this->addXpRecord($profileXpRecord, \DateTime::createFromFormat('Y-m-d', $day)->format('U'));
        }

        $pp = new ProfilePerformance();
        //Test XP diff within time range with XP records
        $out = $pp->aggregateForTimeRange(
            $this->profile,
            (int) \DateTime::createFromFormat('Y-m-d', $workDays[6])->format('U'),
            (int) \DateTime::createFromFormat('Y-m-d', $workDays[15])->format('U')
        );

        $this->assertEquals(10, $out['xpDiff']);
    }

    /**
     * Test Test profile performance XP difference for time range where there are no XP records
     */
    public function testProfilePerformanceForTimeRangeXpDifferenceWithNoXp()
    {
        $profileXpRecord = $this->getXpRecord();
        $workDays = WorkDays::getWorkDays();
        foreach ($workDays as $day) {
            $this->addXpRecord($profileXpRecord, \DateTime::createFromFormat('Y-m-d', $day)->format('U'));
        }

        $pp = new ProfilePerformance();
        //Test XP diff for time range where there are no XP records
        $startTime = (int) (new \DateTime())->modify('+50 days')->format('U');
        $endTime = (int) (new \DateTime())->modify('+55 days')->format('U');
        $out = $pp->aggregateForTimeRange($this->profile, $startTime, $endTime);

        $this->assertEquals(0, $out['xpDiff']);
    }

    /**
     * Test Test profile performance XP difference for time range of 3 days (2 days are without XP records)
     */
    public function testProfilePerformanceForTimeRangeFiveDaysXpDifference()
    {
        $profileXpRecord = $this->getXpRecord();
        $workDays = WorkDays::getWorkDays();
        foreach ($workDays as $day) {
            $this->addXpRecord($profileXpRecord, \DateTime::createFromFormat('Y-m-d', $day)->format('U'));
        }

        $pp = new ProfilePerformance();
        //Test XP diff when first 2 days of check there are no xp records and 3rd day there is one record
        $twoDaysBeforeFirstWorkDay = (int) (new \DateTime(reset($workDays)))->modify('-2 days')->format('U');
        $firstWorkDay = (int) \DateTime::createFromFormat('Y-m-d', reset($workDays))->format('U');

        $out = $pp->aggregateForTimeRange($this->profile, $twoDaysBeforeFirstWorkDay, $firstWorkDay);

        $this->assertEquals(1, $out['xpDiff']);
    }

    /**
     * Test profile performance for six days time range, with some tasks within time range and out of time range
     */
    public function testProfilePerformanceTaskCalculationDeliveryForTimeRangeSixDays()
    {
        $project = $this->getNewProject();
        $project->save();

        $workDays = WorkDays::getWorkDays();
        $tasks = [];
        $counter = 1;
        foreach ($workDays as $day) {
            $unixDay = \DateTime::createFromFormat('Y-m-d', $day)->format('U');
            $task = $this->getAssignedTask($unixDay);
            $task->estimatedHours = 1;
            $task->project_id = $project->id;
            if ($counter % 2 === 0) {
                $task->passed_qa = true;
                $task->timeFinished = (int) $unixDay;
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
        $unix2DaysAgo = (int) Carbon::now()->subDays(2)->format('U');

        $pp = new ProfilePerformance();

        $this->setExpectedException(
            UserInputException::class,
            'Invalid time range input. Must be type of integer',
            400
        );
        $out = $pp->aggregateForTimeRange($this->profile, $unix2DaysAgo, $unixNow);
        $this->assertEquals($out, $this->getExpectedException());

        // Integer timestamp
        $unixNowInteger = (int) Carbon::now()->format('U');
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
}
