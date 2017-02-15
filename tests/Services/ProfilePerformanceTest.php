<?php

namespace Tests\Services;

use App\Profile;
use App\Services\ProfilePerformance;
use Tests\Collections\ProjectRelated;
use Tests\TestCase;

class ProfilePerformanceTest extends TestCase
{
    use ProjectRelated;

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
                    'qaProgressSeconds' => 0,
                    'qaProgressTotalSeconds' => 0,
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
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
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


        $this->assertEquals(false, $profilePerformanceArray['taskCompleted']);
        $this->assertEquals($minutesWorking * 60, $profilePerformanceArray['workSeconds']);
        $this->assertEquals(0, $profilePerformanceArray['qaSeconds']);
        $this->assertEquals(0, $profilePerformanceArray['pauseSeconds']);
    }
}
