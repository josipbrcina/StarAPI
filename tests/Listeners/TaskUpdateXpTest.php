<?php

namespace Tests\Listeners;

use Tests\TestCase;
use Tests\Collections\ProjectRelated;
use App\Profile;
use App\Events\ModelUpdate;
use App\Listeners\TaskUpdateXP;
use Tests\Collections\ProfileRelated;

class TaskUpdateXpTest extends TestCase
{
    use ProjectRelated, ProfileRelated;

    public function setUp()
    {
        parent::setUp();

        $this->setTaskOwner(Profile::create());
        $this->profile->xp = 200;
        $this->profile->save();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->profile->delete();
    }

    //test update XP on unfinished task
    public function testTaskUpdateXpUnfinishedTask()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        $this->assertEquals(false, $out);
    }

    //early task delivery with speedCoefficient < 0.75
    public function testTaskUpdateXpEarlyTaskDelivery()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);

        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        //qa was 10 mins
        $worked[$task->owner]['qa'] = 10 * 60;

        //task worked 15 mins
        $worked[$task->owner]['worked'] = 15 * 60;

        $task->work = $worked;
        $task->save();
        $task->passed_qa = true;

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(200.2025, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    //late delivery with speedCoefficient > 1 && <= 1.1 which is -1 XP point
    public function testTaskUpdateXpLateTaskDeliveryFirstCase()
    {
        // Assigned 190 mins before
        $minutesWorking = 190;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);

        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        //qa was 5 min
        $worked[$task->owner]['qa'] = 5 * 60;

        //task worked 185 min
        $worked[$task->owner]['worked'] = 185 * 60;

        $task->work = $worked;
        $task->save();
        $task->passed_qa = true;

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(199, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    //late delivery with speedCoefficient > 1.1 && <= 1.25 which is -2 XP point
    public function testTaskUpdateXpLateTaskDeliverySecondCase()
    {
        // Assigned 205 mins before
        $minutesWorking = 205;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);

        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        //qa was 4 min
        $worked[$task->owner]['qa'] = 4 * 60;

        //task worked 201 min
        $worked[$task->owner]['worked'] = 201 * 60;

        $task->work = $worked;
        $task->save();
        $task->passed_qa = true;

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(198, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    //late delivery with speedCoefficient > 1.25 -3 XP point
    public function testTaskUpdateXpLateTaskDeliveryThirdCase()
    {
        // Assigned 400 mins before
        $minutesWorking = 400;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);

        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        //qa was 4 min
        $worked[$task->owner]['qa'] = 4 * 60;

        //task worked 46 min
        $worked[$task->owner]['worked'] = 396 * 60;

        $task->work = $worked;
        $task->save();
        $task->passed_qa = true;

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(197, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    public function testTaskUpdateXpProjectOwnerReviewInTime()
    {
        $project = $this->getNewProject();
        $project->acceptedBy = $this->profile->id;
        $project->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);


        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->project_id = $project->id;
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        //qa was 10 mins
        $worked[$task->owner]['qa'] = 10 * 60;

        //qa_in_progress was 10mins
        $worked[$task->owner]['qa_in_progress'] = 10 * 60;

        //task worked 15 mins
        $worked[$task->owner]['worked'] = 15 * 60;

        $task->work = $worked;
        $task->save();
        $task->passed_qa = true;

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        //task done in time so task owner(admin also) get's double XP
        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(200.4525, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    public function testTaskUpdateXpProjectOwnerReviewLate()
    {
        $project = $this->getNewProject();
        $project->acceptedBy = $this->profile->id;
        $project->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);


        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->project_id = $project->id;
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        //qa was 10 mins
        $worked[$task->owner]['qa'] = 25 * 60 * 60;

        //qa_in_progress was 40mins
        $worked[$task->owner]['qa_in_progress'] = 40 * 60;

        //task worked 15 mins
        $worked[$task->owner]['worked'] = 15 * 60;

        $task->work = $worked;
        $task->save();
        $task->passed_qa = true;

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        //task owner get's XP for early delivery and xp is deducted because code note reviewed in time
        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(197.2025, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    /**
     * Test task update XP with low priority task without any high or medium priority unassigned tasks and with
     * other low priority task
     */
    public function testTaskUpdateXpTaskPriorityOnlyLow()
    {
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        $taskLowPriorityWithoutOwner = $this->getNewTask();
        $taskLowPriorityWithoutOwner->project_id = $project->id;
        $taskLowPriorityWithoutOwner->priority = 'Low';
        $taskLowPriorityWithoutOwner->save();


        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskLowPriority = $this->getAssignedTask($assignedAgo);
        $taskLowPriority->priority = 'Low';
        $taskLowPriority->estimatedHours = 0.6;
        $taskLowPriority->complexity = 5;
        $taskLowPriority->project_id = $project->id;
        $taskLowPriority->save();

        $taskLowPriority->submitted_for_qa = true;
        $worked = $taskLowPriority->work;

        //qa was 10 mins
        $worked[$taskLowPriority->owner]['qa'] = 10 * 60;

        //task worked 15 mins
        $worked[$taskLowPriority->owner]['worked'] = 15 * 60;

        $taskLowPriority->work = $worked;
        $taskLowPriority->save();
        $taskLowPriority->passed_qa = true;

        $event = new ModelUpdate($taskLowPriority);
        $listener = new TaskUpdateXP($taskLowPriority);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(200.2025, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    /**
     * Test task deduct Xp award for low priority task because there are medium and high priority unassigned tasks
     */
    public function testTaskUpdateXpTaskPriorityLowDeduct()
    {
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        $taskMediumPriorityWithoutOwner = $this->getNewTask();
        $taskMediumPriorityWithoutOwner->project_id = $project->id;
        $taskMediumPriorityWithoutOwner->priority = 'Medium';
        $taskMediumPriorityWithoutOwner->save();

        $taskHighPriorityWithoutOwner = $this->getNewTask();
        $taskHighPriorityWithoutOwner->project_id = $project->id;
        $taskHighPriorityWithoutOwner->priority = 'High';
        $taskHighPriorityWithoutOwner->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskLowPriority = $this->getAssignedTask($assignedAgo);
        $taskLowPriority->priority = 'Low';
        $taskLowPriority->estimatedHours = 0.6;
        $taskLowPriority->complexity = 5;
        $taskLowPriority->project_id = $project->id;
        $taskLowPriority->save();

        $taskLowPriority->submitted_for_qa = true;
        $worked = $taskLowPriority->work;

        //qa was 10 mins
        $worked[$taskLowPriority->owner]['qa'] = 10 * 60;

        //task worked 15 mins
        $worked[$taskLowPriority->owner]['worked'] = 15 * 60;

        $taskLowPriority->work = $worked;
        $taskLowPriority->save();
        $taskLowPriority->passed_qa = true;

        $event = new ModelUpdate($taskLowPriority);
        $listener = new TaskUpdateXP($taskLowPriority);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(200.10125, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    /**
     * Test task update XP with medium priorty task, without any unassigned high priority and with unassigned low
     * priority task
     */
    public function testTaskUpdateXpTaskPriorityMediumOrLow()
    {
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        $taskLowPriorityWithoutOwner = $this->getNewTask();
        $taskLowPriorityWithoutOwner->project_id = $project->id;
        $taskLowPriorityWithoutOwner->priority = 'Low';
        $taskLowPriorityWithoutOwner->save();


        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskMediumPriority = $this->getAssignedTask($assignedAgo);
        $taskMediumPriority->priority = 'Medium';
        $taskMediumPriority->estimatedHours = 0.6;
        $taskMediumPriority->complexity = 5;
        $taskMediumPriority->project_id = $project->id;
        $taskMediumPriority->save();

        $taskMediumPriority->submitted_for_qa = true;
        $worked = $taskMediumPriority->work;

        //qa was 10 mins
        $worked[$taskMediumPriority->owner]['qa'] = 10 * 60;

        //task worked 15 mins
        $worked[$taskMediumPriority->owner]['worked'] = 15 * 60;

        $taskMediumPriority->work = $worked;
        $taskMediumPriority->save();
        $taskMediumPriority->passed_qa = true;

        $event = new ModelUpdate($taskMediumPriority);
        $listener = new TaskUpdateXP($taskMediumPriority);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(200.2025, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    /**
     * Test task deduct XP award for medium priorty task because there is unassigned high priority task
     */
    public function testTaskUpdateXpTaskPriorityMediumDeduct()
    {
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        $taskHighPriorityWithoutOwner = $this->getNewTask();
        $taskHighPriorityWithoutOwner->project_id = $project->id;
        $taskHighPriorityWithoutOwner->priority = 'High';
        $taskHighPriorityWithoutOwner->save();

        $taskMediumPriorityWithoutOwner = $this->getNewTask();
        $taskMediumPriorityWithoutOwner->project_id = $project->id;
        $taskMediumPriorityWithoutOwner->priority = 'Medium';
        $taskMediumPriorityWithoutOwner->save();


        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskMediumPriority = $this->getAssignedTask($assignedAgo);
        $taskMediumPriority->priority = 'Medium';
        $taskMediumPriority->estimatedHours = 0.6;
        $taskMediumPriority->complexity = 5;
        $taskMediumPriority->project_id = $project->id;
        $taskMediumPriority->save();

        $taskMediumPriority->submitted_for_qa = true;
        $worked = $taskMediumPriority->work;

        //qa was 10 mins
        $worked[$taskMediumPriority->owner]['qa'] = 10 * 60;

        //task worked 15 mins
        $worked[$taskMediumPriority->owner]['worked'] = 15 * 60;

        $taskMediumPriority->work = $worked;
        $taskMediumPriority->save();
        $taskMediumPriority->passed_qa = true;

        $event = new ModelUpdate($taskMediumPriority);
        $listener = new TaskUpdateXP($taskMediumPriority);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(200.162, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    /**
     * Test task update XP with high priority task
     */
    public function testTaskUpdateXpTaskPriorityHigh()
    {
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        $taskHighPriorityWithoutOwner = $this->getNewTask();
        $taskHighPriorityWithoutOwner->project_id = $project->id;
        $taskHighPriorityWithoutOwner->priority = 'High';
        $taskHighPriorityWithoutOwner->save();

        $taskMediumPriorityWithoutOwner = $this->getNewTask();
        $taskMediumPriorityWithoutOwner->project_id = $project->id;
        $taskMediumPriorityWithoutOwner->priority = 'Medium';
        $taskMediumPriorityWithoutOwner->save();


        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskHighPriority = $this->getAssignedTask($assignedAgo);
        $taskHighPriority->priority = 'High';
        $taskHighPriority->estimatedHours = 0.6;
        $taskHighPriority->complexity = 5;
        $taskHighPriority->project_id = $project->id;
        $taskHighPriority->save();

        $taskHighPriority->submitted_for_qa = true;
        $worked = $taskHighPriority->work;

        //qa was 10 mins
        $worked[$taskHighPriority->owner]['qa'] = 10 * 60;

        //task worked 15 mins
        $worked[$taskHighPriority->owner]['worked'] = 15 * 60;

        $taskHighPriority->work = $worked;
        $taskHighPriority->save();
        $taskHighPriority->passed_qa = true;

        $event = new ModelUpdate($taskHighPriority);
        $listener = new TaskUpdateXP($taskHighPriority);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(200.2025, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }
}
