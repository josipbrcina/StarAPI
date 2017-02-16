<?php

namespace Tests\Services;

use App\Helpers\WorkDays;
use App\Profile;
use App\Services\ProfilePerformance;
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

        $this->setTaskOwner(new Profile());

        $this->projectOwner = new Profile();

        $this->projectOwner->save();
        $this->profile->save();
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
                    'blockedSeconds' => 0,
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
        $this->assertArrayHasKey('qaSeconds', $profilePerformanceArray);
        $this->assertArrayHasKey('pauseSeconds', $profilePerformanceArray);

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
        $testOne = $pp->aggregateForTimeRange($this->profile,
            \DateTime::createFromFormat('Y-m-d', $workDays[0])->format('U'),
            \DateTime::createFromFormat('Y-m-d', $workDays[4])->format('U'));

        $this->assertEquals(5, $testOne['xpDiff']);
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
        $testTwo = $pp->aggregateForTimeRange($this->profile,
            \DateTime::createFromFormat('Y-m-d', $workDays[6])->format('U'),
            \DateTime::createFromFormat('Y-m-d', $workDays[15])->format('U'));

        $this->assertEquals(10, $testTwo['xpDiff']);

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
        $startTime = (new \DateTime())->modify('+50 days')->format('U');
        $endTime = (new \DateTime())->modify('+55 days')->format('U');
        $testThree = $pp->aggregateForTimeRange($this->profile, $startTime, $endTime);

        $this->assertEquals(0, $testThree['xpDiff']);
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
        $twoDaysBeforeFirstWorkDay = (new \DateTime(reset($workDays)))->modify('-2 days')->format('U');
        $firstWorkDay = \DateTime::createFromFormat('Y-m-d', reset($workDays))->format('U');

        $testFour = $pp->aggregateForTimeRange($this->profile, $twoDaysBeforeFirstWorkDay, $firstWorkDay);

        $this->assertEquals(1, $testFour['xpDiff']);
    }
}
