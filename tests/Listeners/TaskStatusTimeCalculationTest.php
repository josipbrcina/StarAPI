<?php

namespace Tests\Listeners;

use App\Events\TaskStatusTimeCalculation;
use App\GenericModel;
use Tests\Collections\ProfileRelated;
use Tests\Collections\ProjectRelated;
use Tests\TestCase;
use App\Profile;

class TaskStatusTimeCalculationTest extends TestCase
{
    use ProjectRelated, ProfileRelated;

    public function setUp()
    {
        parent::setUp();

        $this->profile = Profile::create();

        $this->profile->save();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->profile->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Test methods
    |--------------------------------------------------------------------------
    |
    | Here are methods to test TaskStatusTimeCalculation listener in different cases
    */

    /**
     * Test task status time for model with wrong collection
     */
    public function testTaskStatusTimeWrongCollection()
    {
        $project = $this->getNewProject();
        $event = new TaskStatusTimeCalculation($project);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $out = $listener->handle($event);
        $this->assertEquals(false, $out);
    }

    /**
     * Test task status time for new task without owner
     */
    public function testTaskStatusTimeNewTaskWithoutOwner()
    {
        $task = $this->getNewTask();
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $out = $listener->handle($event);
        $this->assertEquals(false, $out);
    }

    /**
     * Test task status time with new task that has owner
     */
    public function teskTastStatusTimeNewTaskWithOwner()
    {
        $task = $this->getNewTask();
        $task->owner = $this->profile;
        $task->save();
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertArrayHasKey('worked', $task->work[$task->owner]);
        $this->assertArrayHasKey('paused', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_in_progress', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_total_time', $task->work[$task->owner]);
        $this->assertArrayHasKey('blocked', $task->work[$task->owner]);
        $this->assertArrayHasKey('workTrackTimestamp', $task->work[$task->owner]);
        $this->assertArrayHasKey('timeAssigned', $task->work[$task->owner]);
        $this->assertEquals(0, $task->work[$task->owner]['worked']);
        $this->assertEquals(0, $task->work[$task->owner]['paused']);
        $this->assertEquals(0, $task->work[$task->owner]['qa']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_in_progress']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_total_time']);
        $this->assertEquals(0, $task->work[$task->owner]['blocked']);
        $this->assertEquals($task->work[$task->owner]['workTrackTimestamp'], $task->work[$task->owner]['timeAssigned']);
    }

    /**
     * Test task status time for task assigned
     */
    public function testTaskStatusTimeCalculationForTaskAssigned()
    {
        $task = $this->getNewTask();
        $task->owner = $this->profile->id;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertArrayHasKey('worked', $task->work[$task->owner]);
        $this->assertArrayHasKey('paused', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_in_progress', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_total_time', $task->work[$task->owner]);
        $this->assertArrayHasKey('blocked', $task->work[$task->owner]);
        $this->assertArrayHasKey('workTrackTimestamp', $task->work[$task->owner]);
        $this->assertArrayHasKey('timeAssigned', $task->work[$task->owner]);
        $this->assertEquals(0, $task->work[$task->owner]['worked']);
        $this->assertEquals(0, $task->work[$task->owner]['paused']);
        $this->assertEquals(0, $task->work[$task->owner]['qa']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_in_progress']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_total_time']);
        $this->assertEquals(0, $task->work[$task->owner]['blocked']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'],
            $task->work[$task->owner]['timeAssigned']
        );
    }

    /**
     * Test task status time for task reassigned
     */
    public function testTaskStatusTimeCalculationForTaskReassigned()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->save();
        $oldOwner = $task->owner;
        $oldWorkTrackTimestamp = $task->work[$oldOwner]['workTrackTimestamp'];
        $newOwner = Profile::create();
        $task->owner = $newOwner->id;

        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);
        $task->save();

        $this->assertCount(2, $task->work);
        $this->assertArrayHasKey($newOwner->id, $task->work);

        $this->assertArrayHasKey('worked', $task->work[$oldOwner]);
        $this->assertArrayHasKey('paused', $task->work[$oldOwner]);
        $this->assertArrayHasKey('qa', $task->work[$oldOwner]);
        $this->assertArrayHasKey('qa_in_progress', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_total_time', $task->work[$task->owner]);
        $this->assertArrayHasKey('blocked', $task->work[$task->owner]);
        $this->assertArrayHasKey('workTrackTimestamp', $task->work[$oldOwner]);
        $this->assertArrayHasKey('timeAssigned', $task->work[$oldOwner]);
        $this->assertArrayHasKey('timeRemoved', $task->work[$oldOwner]);
        $this->assertEquals(
            $task->work[$oldOwner]['workTrackTimestamp'] - $oldWorkTrackTimestamp,
            $task->work[$oldOwner]['worked']
        );
        $this->assertEquals(0, $task->work[$oldOwner]['paused']);
        $this->assertEquals(0, $task->work[$oldOwner]['qa']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_in_progress']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_total_time']);
        $this->assertEquals(0, $task->work[$oldOwner]['blocked']);
        $this->assertEquals(
            $task->work[$oldOwner]['workTrackTimestamp'],
            $task->work[$oldOwner]['timeRemoved']
        );

        $this->assertArrayHasKey('worked', $task->work[$task->owner]);
        $this->assertArrayHasKey('paused', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_in_progress', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_total_time', $task->work[$task->owner]);
        $this->assertArrayHasKey('blocked', $task->work[$task->owner]);
        $this->assertArrayHasKey('workTrackTimestamp', $task->work[$task->owner]);
        $this->assertArrayHasKey('timeAssigned', $task->work[$task->owner]);
        $this->assertArrayNotHasKey('timeRemoved', $task->work[$task->owner]);
        $this->assertEquals(0, $task->work[$task->owner]['worked']);
        $this->assertEquals(0, $task->work[$task->owner]['paused']);
        $this->assertEquals(0, $task->work[$task->owner]['qa']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_in_progress']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_total_time']);
        $this->assertEquals(0, $task->work[$task->owner]['blocked']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'],
            $task->work[$task->owner]['timeAssigned']
        );
    }

    /**
     * Test task status time for paused
     */
    public function testTaskStatusTimeCalculationForTaskPaused()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->save();
        $workedTimeBeforeListener = $task->work[$task->owner]['worked'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->paused = true;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertGreaterThan($workedTimeBeforeListener, $task->work[$task->owner]['worked']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['worked']
        );
    }

    /**
     * Test task status time for resumed
     */
    public function testTaskStatusTimeCalculationForTaskResumed()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->paused = true;
        $task->save();
        $pausedTimeBeforeListener = $task->work[$task->owner]['paused'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->paused = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertGreaterThan($pausedTimeBeforeListener, $task->work[$task->owner]['paused']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['paused']
        );
    }

    /**
     * Test task status time for blocked
     */
    public function testTaskStatusTimeCalculationForBlocked()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->save();
        $workedTimeBeforeListener = $task->work[$task->owner]['worked'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->blocked = true;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertGreaterThan($workedTimeBeforeListener, $task->work[$task->owner]['worked']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['worked']
        );
    }

    /**
     * Test task status time for unblocked
     */
    public function testTaskStatusTimeCalculationForUnBlocked()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->blocked = true;
        $task->save();
        $blockedTimeBeforeListener = $task->work[$task->owner]['blocked'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->blocked = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertGreaterThan($blockedTimeBeforeListener, $task->work[$task->owner]['blocked']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['blocked']
        );
    }

    /**
     * Test task status time for submitted for QA
     */
    public function testTaskStatusTimeCalculationForSubmittedForQa()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->save();

        $workedTimeBeforeListener = $task->work[$task->owner]['worked'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->submitted_for_qa = true;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertGreaterThan($workedTimeBeforeListener, $task->work[$task->owner]['worked']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['worked']
        );
    }

    /**
     * Test task status time when task submitted for QA progress
     */
    public function testTaskStatusTimeCalculationForQaProgress()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->submitted_for_qa = true;
        $task->save();

        $qaTimeBeforeListener = $task->work[$task->owner]['qa'];
        $qaProgressTimeBeforeListener = $task->work[$task->owner]['qa_in_progress'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->qa_in_progress = true;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertEquals(0, $qaProgressTimeBeforeListener);
        $this->assertEquals(0, $task->work[$task->owner]['qa_total_time']);
        $this->assertGreaterThan($qaTimeBeforeListener, $task->work[$task->owner]['qa']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['qa']
        );
    }

    /**
     * Test task status time for failed QA
     */
    public function testTaskStatusTimeCalculationForFailedQa()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->qa_in_progress = true;
        $task->save();
        $qaTimeBeforeListener = $task->work[$task->owner]['qa'];
        $qaProgressTimeBeforeListener = $task->work[$task->owner]['qa_in_progress'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->qa_in_progress = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertEquals(0, $qaTimeBeforeListener);
        $this->assertEquals($task->work[$task->owner]['qa_in_progress'], $qaProgressTimeBeforeListener);
        $this->assertEquals(0, $task->work[$task->owner]['qa_in_progress']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['qa_total_time']
        );
    }

    /**
     * Test task status time for passed QA
     */
    public function testTaskStatusTimeCalculationForPassedQa()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->qa_in_progress = true;
        $task->save();
        $qaProgressTimeBeforeListener = $task->work[$task->owner]['qa'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->passed_qa = true;
        $task->qa_in_progress = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);
        $task->save();

        $this->assertGreaterThan($qaProgressTimeBeforeListener, $task->work[$task->owner]['qa_in_progress']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['qa_in_progress']
        );
        $this->assertEquals($task->work[$task->owner]['qa_in_progress'], $task->work[$task->owner]['qa_total_time']);
    }

    /**
     * Test task status time calculation complex flow (Reassigned, paused, blocked, qa, fail qa and finally done)
     */
    public function testTaskStatusTimeComplexFlowTaskDone()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);
        $task->save();
        $workedTimeBeforeListener = $task->work[$task->owner]['worked'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->paused = true;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);


        $this->assertGreaterThan($workedTimeBeforeListener, $task->work[$task->owner]['worked']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['worked']
        );

        $modifyTimeStamp = $task->work;
        $modifyTimeStamp[$task->owner]['workTrackTimestamp'] = (new \DateTime())->format('U') - 20 * 60;
        $task->work = $modifyTimeStamp;
        $task->save();
        //task resumed
        $pausedTimeBeforeListener = $task->work[$task->owner]['paused'];
        $timeStampBeforeResumed = $task->work[$task->owner]['workTrackTimestamp'];

        $task->paused = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);


        $this->assertGreaterThan($pausedTimeBeforeListener, $task->work[$task->owner]['paused']);
        $this->assertGreaterThan($timeStampBeforeResumed, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            ($task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeResumed),
            $task->work[$task->owner]['paused']
        );

        $modifyTimeStamp = $task->work;
        $modifyTimeStamp[$task->owner]['workTrackTimestamp'] = (new \DateTime())->format('U') - 15 * 60;
        $task->work = $modifyTimeStamp;
        $task->save();

        //task reassigned
        $oldOwner = $task->owner;
        $oldWorkTrackTimestamp = $task->work[$oldOwner]['workTrackTimestamp'];
        $oldWorked = $task->work[$oldOwner]['worked'];
        $oldWorkPaused = $task->work[$oldOwner]['paused'];
        $oldWorkQa = $task->work[$oldOwner]['qa'];
        $oldWorkBlocked = $task->work[$oldOwner]['blocked'];
        $oldWorkQaProgress = $task->work[$oldOwner]['qa_in_progress'];
        $oldWorkQaProgressTotal = $task->work[$oldOwner]['qa_total_time'];
        $newOwner = Profile::create();
        $task->owner = $newOwner->id;

        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertCount(2, $task->work);
        $this->assertArrayHasKey($newOwner->id, $task->work);

        $this->assertArrayHasKey('worked', $task->work[$oldOwner]);
        $this->assertArrayHasKey('paused', $task->work[$oldOwner]);
        $this->assertArrayHasKey('qa', $task->work[$oldOwner]);
        $this->assertArrayHasKey('qa_in_progress', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_total_time', $task->work[$task->owner]);
        $this->assertArrayHasKey('blocked', $task->work[$task->owner]);
        $this->assertArrayHasKey('workTrackTimestamp', $task->work[$oldOwner]);
        $this->assertArrayHasKey('timeAssigned', $task->work[$oldOwner]);
        $this->assertArrayHasKey('timeRemoved', $task->work[$oldOwner]);
        $this->assertEquals(
            $task->work[$oldOwner]['workTrackTimestamp'] - $oldWorkTrackTimestamp + $oldWorked,
            $task->work[$oldOwner]['worked']
        );
        $this->assertEquals($oldWorkPaused, $task->work[$oldOwner]['paused']);
        $this->assertEquals($oldWorkQa, $task->work[$oldOwner]['qa']);
        $this->assertEquals($oldWorkQaProgress, $task->work[$oldOwner]['qa_in_progress']);
        $this->assertEquals($oldWorkQaProgressTotal, $task->work[$oldOwner]['qa_total_time']);
        $this->assertEquals($oldWorkBlocked, $task->work[$oldOwner]['blocked']);
        $this->assertEquals(
            $task->work[$oldOwner]['workTrackTimestamp'],
            $task->work[$oldOwner]['timeRemoved']
        );

        $this->assertArrayHasKey('worked', $task->work[$task->owner]);
        $this->assertArrayHasKey('paused', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_in_progress', $task->work[$task->owner]);
        $this->assertArrayHasKey('qa_total_time', $task->work[$task->owner]);
        $this->assertArrayHasKey('blocked', $task->work[$task->owner]);
        $this->assertArrayHasKey('workTrackTimestamp', $task->work[$task->owner]);
        $this->assertArrayHasKey('timeAssigned', $task->work[$task->owner]);
        $this->assertArrayNotHasKey('timeRemoved', $task->work[$task->owner]);
        $this->assertEquals(0, $task->work[$task->owner]['worked']);
        $this->assertEquals(0, $task->work[$task->owner]['paused']);
        $this->assertEquals(0, $task->work[$task->owner]['qa']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_in_progress']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_total_time']);
        $this->assertEquals(0, $task->work[$task->owner]['blocked']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'],
            $task->work[$task->owner]['timeAssigned']
        );

        $modifyTimeStamp = $task->work;
        $modifyTimeStamp[$task->owner]['workTrackTimestamp'] = (new \DateTime())->format('U') - 13 * 60;
        $modifyTimeStamp[$task->owner]['assignedTime'] = (new \DateTime())->format('U') - 13 * 60;
        $task->work = $modifyTimeStamp;
        $task->save();

        //qa ready
        $workedTimeBeforeListener = $task->work[$task->owner]['worked'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->submitted_for_qa = true;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertGreaterThan($workedTimeBeforeListener, $task->work[$task->owner]['worked']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['worked']
        );

        $modifyTimeStamp = $task->work;
        $modifyTimeStamp[$task->owner]['workTrackTimestamp'] = (new \DateTime())->format('U') - 11 * 60;
        $task->work = $modifyTimeStamp;
        $task->save();

        //task added to QA progress

        $qaTimeBeforeListener = $task->work[$task->owner]['qa'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->qa_in_progress = true;
        $task->submitted_for_qa = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertEquals(0, $task->work[$task->owner]['qa_in_progress']);
        $this->assertEquals(0, $task->work[$task->owner]['qa_total_time']);
        $this->assertGreaterThan($qaTimeBeforeListener, $task->work[$task->owner]['qa']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['qa']
        );

        $modifyTimeStamp = $task->work;
        $modifyTimeStamp[$task->owner]['workTrackTimestamp'] = (new \DateTime())->format('U') - 9 * 60;
        $task->work = $modifyTimeStamp;
        $task->save();

        //qa failed
        $qaProgressTimeBeforeListener = $task->work[$task->owner]['qa_in_progress'];
        $qaProgressTotalTimeBeforeListener = $task->work[$task->owner]['qa_total_time'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->qa_in_progress = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertEquals($qaProgressTimeBeforeListener, $task->work[$task->owner]['qa_in_progress']);
        //when task failed QA qa_in_progress should be zero
        $this->assertEquals(0, $task->work[$task->owner]['qa_in_progress']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertGreaterThan($qaProgressTotalTimeBeforeListener, $task->work[$task->owner]['qa_total_time']);
        //check QA total time when task failed QA
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['qa_total_time']
        );

        $modifyTimeStamp = $task->work;
        $modifyTimeStamp[$task->owner]['workTrackTimestamp'] = (new \DateTime())->format('U') - 8 * 60;
        $task->work = $modifyTimeStamp;
        $task->paused = true;
        $task->save();

        //task resumed

        $pausedTimeBeforeListener = $task->work[$task->owner]['paused'];
        $timeStampBeforeResumed = $task->work[$task->owner]['workTrackTimestamp'];

        $task->paused = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);


        $this->assertGreaterThan($pausedTimeBeforeListener, $task->work[$task->owner]['paused']);
        $this->assertGreaterThan($timeStampBeforeResumed, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals(
            ($task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeResumed),
            $task->work[$task->owner]['paused']
        );

        $modifyTimeStamp = $task->work;
        $modifyTimeStamp[$task->owner]['workTrackTimestamp'] = (new \DateTime())->format('U') - 6 * 60;
        $task->work = $modifyTimeStamp;
        $task->save();

        //task submitted for qa again
        $workedTimeBeforeListener = $task->work[$task->owner]['worked'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->submitted_for_qa = true;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertGreaterThan($workedTimeBeforeListener, $task->work[$task->owner]['worked']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertEquals($task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener
            + $workedTimeBeforeListener, $task->work[$task->owner]['worked']);

        $modifyTimeStamp = $task->work;
        $modifyTimeStamp[$task->owner]['workTrackTimestamp'] = (new \DateTime())->format('U') - 5 * 60;
        $task->work = $modifyTimeStamp;
        $task->save();

        //task added to QA progress again

        $qaTimeBeforeListener = $task->work[$task->owner]['qa'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];
        $qaProgressTotalTimeBeforeListener = $task->work[$task->owner]['qa_total_time'];

        $task->qa_in_progress = true;
        $task->submitted_for_qa = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertEquals(0, $task->work[$task->owner]['qa_in_progress']);
        $this->assertEquals($qaProgressTotalTimeBeforeListener, $task->work[$task->owner]['qa_total_time']);
        $this->assertGreaterThan($qaTimeBeforeListener, $task->work[$task->owner]['qa']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        //check QA time
        $this->assertEquals($task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener
            + $qaTimeBeforeListener, $task->work[$task->owner]['qa']);

        $modifyTimeStamp = $task->work;
        $modifyTimeStamp[$task->owner]['workTrackTimestamp'] = (new \DateTime())->format('U') - 2 * 60;
        $task->work = $modifyTimeStamp;
        $task->save();

        //finally QA passed
        $qaProgressTimeBeforeListener = $task->work[$task->owner]['qa_in_progress'];
        $qaProgressTotalTimeBeforeListener = $task->work[$task->owner]['qa_total_time'];
        $timeStampBeforeListener = $task->work[$task->owner]['workTrackTimestamp'];

        $task->passed_qa = true;
        $task->qa_in_progress = false;
        $event = new TaskStatusTimeCalculation($task);
        $listener = new \App\Listeners\TaskStatusTimeCalculation();
        $listener->handle($event);

        $this->assertGreaterThan($qaProgressTimeBeforeListener, $task->work[$task->owner]['qa_in_progress']);
        $this->assertGreaterThan($timeStampBeforeListener, $task->work[$task->owner]['workTrackTimestamp']);
        $this->assertGreaterThan($qaProgressTotalTimeBeforeListener, $task->work[$task->owner]['qa_total_time']);
        //check QA total time and QA in progress time
        $this->assertEquals($task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener
            + $qaProgressTotalTimeBeforeListener, $task->work[$task->owner]['qa_total_time']);
        $this->assertEquals(
            $task->work[$task->owner]['workTrackTimestamp'] - $timeStampBeforeListener,
            $task->work[$task->owner]['qa_in_progress']
        );
    }
}
