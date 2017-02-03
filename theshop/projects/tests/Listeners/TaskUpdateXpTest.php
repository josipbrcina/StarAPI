<?php

namespace TheShop\Tests\Listeners;

use TheShop\Projects\Events\ModelUpdate;
use TheShop\Projects\Listeners\TaskUpdateXP;
use App\Profile;
use TheShop\Tests\Collections\ProjectRelated;
use Tests\TestCase;

class TaskUpdateXpTest extends TestCase
{
    use ProjectRelated;

    private $projectOwner = null;

    public function setUp()
    {
        parent::setUp();

        $this->profile = Profile::create();

        $this->setTaskOwner($this->profile);

        $this->projectOwner = Profile::create();

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
     * Test invalid login attempt
     */
    public function testEmptyTaskHistory()
    {
        $event = new ModelUpdate($this->getTaskWithEmptyHistory());
        $listener = new TaskUpdateXP();

        $out = $listener->handle($event);

        $this->assertEquals(true, $out);
    }

    /**
     * Test invalid login attempt
     */
    public function testTaskJustClaimed()
    {
        $task = $this->getTaskWithJustClaimedHistory();

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP();

        $out = $listener->handle($event);

        $this->assertEquals(false, $out);
    }

    /**
     * Test invalid login attempt
     */
    public function testTaskJustAssigned()
    {
        $task = $this->getTaskWithJustAssignedHistory();

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP();

        $out = $listener->handle($event);

        $this->assertEquals(false, $out);
    }

    /**
     * Test invalid login attempt
     */
    public function testTaskPaused()
    {
        $task = $this->getAssignedAndPausedTask();

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP();

        $out = $listener->handle($event);

        $this->assertEquals(false, $out);
    }

    public function testTaskResumed()
    {
        $task = $this->getResumedTask();

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP();

        $out = $listener->handle($event);

        $this->assertEquals(false, $out);
    }

    /**
     * Test invalid login attempt
     */
    public function testTaskSubmittedForQa()
    {
        $task = $this->getQaSubmittedTask();

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP();

        $out = $listener->handle($event);

        $this->assertEquals(false, $out);
    }

    /**
     * Test invalid login attempt
     */
    public function testTaskQaFail()
    {
        $task = $this->getQaFailTask();

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP();

        $out = $listener->handle($event);

        $this->assertEquals(false, $out);
    }

    /**
     * Test invalid login attempt
     */
    public function testTaskQaFailMultipleTimes()
    {
        $task = $this->getMultipleQaFailTask();

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP();

        $out = $listener->handle($event);

        $this->assertEquals(false, $out);
    }

    /**
     * Test invalid login attempt
     */
    public function testTaskQaSuccess()
    {
        $task = $this->getQaPassedTask();

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP();

        $out = $listener->handle($event);

        $this->assertEquals(true, $out);
    }
}
