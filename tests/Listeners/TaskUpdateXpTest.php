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

        $this->setTaskOwner(Profile::create([
            'skills' => ['PHP']
        ]));
        $this->profile->xp = 200;
        $this->profile->save();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->profile->delete();
    }

    /**
     * test update XP on unfinished task
     */
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

    /**
     * Test task delivery day earlier then due_date
     */
    public function testTaskUpdateXpEarlyTaskDelivery()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);

        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->due_date = (new \DateTime())->modify('+1 day')->format('U');
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        $passedQaAgo = 5;
        $worked[$task->owner]['workTrackTimestamp'] =
            (int) (new \DateTime())->sub(new \DateInterval('PT' . $passedQaAgo . 'M'))->format('U');

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

    /**
     * Test task delivery on due_date
     */
    public function testTaskUpdateXpDeliveredOnTaskDueDate()
    {
        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);

        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->due_date = (new \DateTime())->format('U');
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        $passedQaAgo = 5;
        $worked[$task->owner]['workTrackTimestamp'] =
            (int) (new \DateTime())->sub(new \DateInterval('PT' . $passedQaAgo . 'M'))->format('U');

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

    /**
     * Test task late delivery
     */
    public function testTaskUpdateXpLateTaskDelivery()
    {
        // Assigned 30 mins before
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);

        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->due_date = (new \DateTime())->modify('+1 day')->format('U');
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        // Task finished after 2 days
        $worked[$task->owner]['workTrackTimestamp'] = (int) (new \DateTime())->modify('+2 days')->format('U');

        $task->work = $worked;
        $task->save();
        $task->passed_qa = true;

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(195, $checkXpProfile->xp);
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
        $task->due_date = (new \DateTime())->modify('+1 day')->format('U');
        $task->project_id = $project->id;
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        // Qa was 10 mins
        $worked[$task->owner]['qa'] = 10 * 60;

        // qa_in_progress was 10 mins
        $worked[$task->owner]['qa_in_progress'] = 10 * 60;

        // Task worked 15 mins
        $worked[$task->owner]['worked'] = 15 * 60;

        $passedQaAgo = 5;
        $worked[$task->owner]['workTrackTimestamp'] =
            (int) (new \DateTime())->sub(new \DateInterval('PT' . $passedQaAgo . 'M'))->format('U');


        $task->work = $worked;
        $task->save();
        $task->passed_qa = true;

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        // Task done in time so task owner(admin also) get's double XP
        $checkXpProfile = Profile::find($this->profile->id);
        $this->assertEquals(200.4525, $checkXpProfile->xp);
        $this->assertEquals(true, $out);
    }

    public function testTaskUpdateXpProjectOwnerReviewLate()
    {
        $project = $this->getNewProject();
        $project->acceptedBy = $this->profile->id;
        $project->save();

        // Assigned 60 minutes ago
        $minutesWorking = 60;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');
        $task = $this->getAssignedTask($assignedAgo);

        $task->estimatedHours = 0.6;
        $task->complexity = 5;
        $task->due_date = (new \DateTime())->modify('+1 day')->format('U');
        $task->project_id = $project->id;
        $task->save();

        $task->submitted_for_qa = true;
        $worked = $task->work;

        // Qa was 10 mins
        $worked[$task->owner]['qa'] = 10 * 60;

        // qa_in_progress was 35 mins
        $worked[$task->owner]['qa_in_progress'] = 35 * 60;

        // Task worked 10 mins
        $worked[$task->owner]['worked'] = 10 * 60;

        $passedQaAgo = 5;
        $worked[$task->owner]['workTrackTimestamp'] =
            (int) (new \DateTime())->sub(new \DateInterval('PT' . $passedQaAgo . 'M'))->format('U');

        $task->work = $worked;
        $task->save();
        $task->passed_qa = true;

        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        // Task owner get's XP for early delivery and xp is deducted because code note reviewed in time
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

        $skillSet = ['PHP'];
        $taskLowPriorityWithoutOwner = $this->getNewTask();
        $taskLowPriorityWithoutOwner->project_id = $project->id;
        $taskLowPriorityWithoutOwner->priority = 'Low';
        $taskLowPriorityWithoutOwner->skillset = $skillSet;
        $taskLowPriorityWithoutOwner->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskLowPriority = $this->getAssignedTask($assignedAgo);
        $taskLowPriority->priority = 'Low';
        $taskLowPriority->estimatedHours = 0.6;
        $taskLowPriority->complexity = 5;
        $taskLowPriority->due_date = (new \DateTime())->modify('+1 day')->format('U');
        $taskLowPriority->project_id = $project->id;
        $taskLowPriority->skillset = $skillSet;
        $taskLowPriority->save();

        $taskLowPriority->submitted_for_qa = true;
        $worked = $taskLowPriority->work;

        // Qa was 10 mins
        $worked[$taskLowPriority->owner]['qa'] = 10 * 60;

        // Task worked 15 mins
        $worked[$taskLowPriority->owner]['worked'] = 15 * 60;

        $passedQaAgo = 5;
        $worked[$taskLowPriority->owner]['workTrackTimestamp'] =
            (int) (new \DateTime())->sub(new \DateInterval('PT' . $passedQaAgo . 'M'))->format('U');

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

        $skillSet = [
            'PHP',
            'React',
            'DevOps'
        ];

        $taskMediumPriorityWithoutOwner = $this->getNewTask();
        $taskMediumPriorityWithoutOwner->project_id = $project->id;
        $taskMediumPriorityWithoutOwner->priority = 'Medium';
        $taskMediumPriorityWithoutOwner->skillset = $skillSet;
        $taskMediumPriorityWithoutOwner->save();

        $taskHighPriorityWithoutOwner = $this->getNewTask();
        $taskHighPriorityWithoutOwner->project_id = $project->id;
        $taskHighPriorityWithoutOwner->priority = 'High';
        $taskHighPriorityWithoutOwner->skillset = $skillSet;
        $taskHighPriorityWithoutOwner->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskLowPriority = $this->getAssignedTask($assignedAgo);
        $taskLowPriority->priority = 'Low';
        $taskLowPriority->estimatedHours = 0.6;
        $taskLowPriority->complexity = 5;
        $taskLowPriority->due_date = (new \DateTime())->modify('+1 day')->format('U');
        $taskLowPriority->project_id = $project->id;
        $taskLowPriority->skillset = $skillSet;
        $taskLowPriority->save();

        $taskLowPriority->submitted_for_qa = true;
        $worked = $taskLowPriority->work;

        // Qa was 10 mins
        $worked[$taskLowPriority->owner]['qa'] = 10 * 60;

        // Task worked 15 mins
        $worked[$taskLowPriority->owner]['worked'] = 15 * 60;

        $passedQaAgo = 5;
        $worked[$taskLowPriority->owner]['workTrackTimestamp'] =
            (int) (new \DateTime())->sub(new \DateInterval('PT' . $passedQaAgo . 'M'))->format('U');

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
     * Test task update XP with medium priority task, without any unassigned high priority and with unassigned low
     * priority task
     */
    public function testTaskUpdateXpTaskPriorityMediumOrLow()
    {
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        $skillSet = [
            'PHP',
            'React',
            'DevOps'
        ];

        $taskLowPriorityWithoutOwner = $this->getNewTask();
        $taskLowPriorityWithoutOwner->project_id = $project->id;
        $taskLowPriorityWithoutOwner->priority = 'Low';
        $taskLowPriorityWithoutOwner->skillset = $skillSet;
        $taskLowPriorityWithoutOwner->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskMediumPriority = $this->getAssignedTask($assignedAgo);
        $taskMediumPriority->priority = 'Medium';
        $taskMediumPriority->estimatedHours = 0.6;
        $taskMediumPriority->complexity = 5;
        $taskMediumPriority->due_date = (new \DateTime())->modify('+1 day')->format('U');
        $taskMediumPriority->project_id = $project->id;
        $taskMediumPriority->skillset = $skillSet;
        $taskMediumPriority->save();

        $taskMediumPriority->submitted_for_qa = true;
        $worked = $taskMediumPriority->work;

        // Qa was 10 mins
        $worked[$taskMediumPriority->owner]['qa'] = 10 * 60;

        // Task worked 15 mins
        $worked[$taskMediumPriority->owner]['worked'] = 15 * 60;

        $passedQaAgo = 5;
        $worked[$taskMediumPriority->owner]['workTrackTimestamp'] =
            (int) (new \DateTime())->sub(new \DateInterval('PT' . $passedQaAgo . 'M'))->format('U');

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
     * Test task deduct XP award for medium priority task because there is unassigned high priority task
     */
    public function testTaskUpdateXpTaskPriorityMediumDeduct()
    {
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        $skillSet = [
            'PHP',
            'React',
            'DevOps'
        ];

        $taskHighPriorityWithoutOwner = $this->getNewTask();
        $taskHighPriorityWithoutOwner->project_id = $project->id;
        $taskHighPriorityWithoutOwner->priority = 'High';
        $taskHighPriorityWithoutOwner->skillset = $skillSet;
        $taskHighPriorityWithoutOwner->save();

        $taskMediumPriorityWithoutOwner = $this->getNewTask();
        $taskMediumPriorityWithoutOwner->project_id = $project->id;
        $taskMediumPriorityWithoutOwner->priority = 'Medium';
        $taskMediumPriorityWithoutOwner->skillset = $skillSet;
        $taskMediumPriorityWithoutOwner->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskMediumPriority = $this->getAssignedTask($assignedAgo);
        $taskMediumPriority->priority = 'Medium';
        $taskMediumPriority->estimatedHours = 0.6;
        $taskMediumPriority->complexity = 5;
        $taskMediumPriority->due_date = (new \DateTime())->modify('+1 day')->format('U');
        $taskMediumPriority->project_id = $project->id;
        $taskMediumPriority->skillset = $skillSet;
        $taskMediumPriority->save();

        $taskMediumPriority->submitted_for_qa = true;
        $worked = $taskMediumPriority->work;

        // Qa was 10 mins
        $worked[$taskMediumPriority->owner]['qa'] = 10 * 60;

        // Task worked 15 mins
        $worked[$taskMediumPriority->owner]['worked'] = 15 * 60;

        $passedQaAgo = 5;
        $worked[$taskMediumPriority->owner]['workTrackTimestamp'] =
            (int) (new \DateTime())->sub(new \DateInterval('PT' . $passedQaAgo . 'M'))->format('U');

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

        $skillSet = [
            'PHP',
            'React',
            'DevOps'
        ];

        $taskHighPriorityWithoutOwner = $this->getNewTask();
        $taskHighPriorityWithoutOwner->project_id = $project->id;
        $taskHighPriorityWithoutOwner->priority = 'High';
        $taskHighPriorityWithoutOwner->skillset = $skillSet;
        $taskHighPriorityWithoutOwner->save();

        $taskMediumPriorityWithoutOwner = $this->getNewTask();
        $taskMediumPriorityWithoutOwner->project_id = $project->id;
        $taskMediumPriorityWithoutOwner->priority = 'Medium';
        $taskMediumPriorityWithoutOwner->skillset = $skillSet;
        $taskMediumPriorityWithoutOwner->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int) (new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $taskHighPriority = $this->getAssignedTask($assignedAgo);
        $taskHighPriority->priority = 'High';
        $taskHighPriority->estimatedHours = 0.6;
        $taskHighPriority->complexity = 5;
        $taskHighPriority->due_date = (new \DateTime())->modify('+1 day')->format('U');
        $taskHighPriority->project_id = $project->id;
        $taskHighPriority->skillset = $skillSet;
        $taskHighPriority->save();

        $taskHighPriority->submitted_for_qa = true;
        $worked = $taskHighPriority->work;

        // Qa was 10 mins
        $worked[$taskHighPriority->owner]['qa'] = 10 * 60;

        // Task worked 15 mins
        $worked[$taskHighPriority->owner]['worked'] = 15 * 60;

        $passedQaAgo = 5;
        $worked[$taskHighPriority->owner]['workTrackTimestamp'] =
            (int) (new \DateTime())->sub(new \DateInterval('PT' . $passedQaAgo . 'M'))->format('U');

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

    /**
     * Test listener for task that has been updated after task passed QA already
     */
    public function testTaskUpdateXpForTaskUpdatedAfterPassedQa()
    {
        $task = $this->getAssignedTask();
        $task->passed_qa = true;
        $task->save();

        // Test finished task without any change
        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        $this->assertEquals(false, $out);


        // Let's make some update
        $task->priority = 'High';
        $task->title = 'Test';

        // Test finished task with some updates
        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        $this->assertEquals(false, $out);

        $task->save();
        $task->passed_qa = false;

        // Test finished task with update passed_qa = false
        $event = new ModelUpdate($task);
        $listener = new TaskUpdateXP($task);
        $out = $listener->handle($event);

        $this->assertEquals(false, $out);
    }
}
