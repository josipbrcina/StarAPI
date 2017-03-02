<?php

namespace Tests\Listeners;

use App\Events\TaskClaim;
use App\Exceptions\UserInputException;
use Tests\Collections\ProfileRelated;
use Tests\Collections\ProjectRelated;
use Tests\TestCase;
use App\Profile;

class TaskClaimTest extends TestCase
{
    use ProjectRelated, ProfileRelated;

    public function setUp()
    {
        parent::setUp();

        $this->setTaskOwner(Profile::create());
        $this->profile->save();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->profile->delete();
    }

    /**
     * Test task claim listener to allow reservation
     */
    public function testTaskClaimListenerAllowReservation()
    {
        // Set user as a member of project that task belongs to
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        // Create new task, update with reservation and trigger event
        $taskToReserve = $this->getNewTask();
        $taskToReserve->project_id = $project->id;
        $taskToReserve->save();
        $reservationsBy = [
            [
                'user_id' => $this->profile->id,
                'timestamp' => (new \DateTime())->format('U')
            ]
        ];
        $taskToReserve->reservationsBy = $reservationsBy;

        $event = new TaskClaim($taskToReserve);
        $listener = new \App\Listeners\TaskClaim();
        $out = $listener->handle($event);

        $this->assertEquals(true, $out);
    }

    /**
     * Test task claim listener to deny reservation cause of previous reserved task within task max reservation time
     */
    public function testTaskClaimListenerDenyReservationPreviousTaskReserved()
    {
        // Set user as a member of project that task belongs to
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        // Set task that is reserved and reservation is within reservation max time
        $previousReservedTask = $this->getNewTask();
        $previousReservedTask->project_id = $project->id;
        $reservationsBy = [
            [
                'user_id' => $this->profile->id,
                'timestamp' => (new \DateTime())->format('U')
            ]
        ];
        $previousReservedTask->reservationsBy = $reservationsBy;
        $previousReservedTask->save();

        // Create new task, update with reservation and trigger event
        $taskToReserve = $this->getNewTask();
        $taskToReserve->project_id = $project->id;
        $taskToReserve->save();
        $reservationsBy = [
            [
                'user_id' => $this->profile->id,
                'timestamp' => (new \DateTime())->format('U')
            ]
        ];
        $taskToReserve->reservationsBy = $reservationsBy;

        $event = new TaskClaim($taskToReserve);
        $listener = new \App\Listeners\TaskClaim();

        $this->setExpectedException(
            UserInputException::class,
            'Permission denied. There is reserved previous task.',
            400
        );
        $out = $listener->handle($event);
        $this->assertEquals($out, $this->getExpectedException());
    }

    /**
     * Test task claim listener to deny reservation if there are unfinished previous tasks
     */
    public function testTaskClaimListenerDenyReservationPreviousUnfinishedTasks()
    {
        // Set user as a member of project that task belongs to
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        // Create new assigned task (unfinished - not blocked, submitted_for_qa, qa_in_progress or passed_qa)
        $unfinishedTask = $this->getAssignedTask();
        $unfinishedTask->save();

        // Create new task, update with reservation and trigger event
        $taskToReserve = $this->getNewTask();
        $taskToReserve->project_id = $project->id;
        $taskToReserve->save();
        $reservationsBy = [
            [
                'user_id' => $this->profile->id,
                'timestamp' => (new \DateTime())->format('U')
            ]
        ];
        $taskToReserve->reservationsBy = $reservationsBy;

        $event = new TaskClaim($taskToReserve);
        $listener = new \App\Listeners\TaskClaim();

        $this->setExpectedException(
            UserInputException::class,
            'Permission denied. There are unfinished previous tasks.',
            400
        );
        $out = $listener->handle($event);
        $this->assertEquals($out, $this->getExpectedException());
    }

    /**
     * Test task claim listener to deny task claim if there are unfinished previous tasks
     */
    public function testTaskClaimListenerDenyTaskClaimPreviousUnfinishedTasks()
    {
        // Set user as a member of project that task belongs to
        $project = $this->getNewProject();
        $members = $project->members;
        $members[] = $this->profile->id;
        $project->members = $members;
        $project->save();

        // Create new assigned task (unfinished - not blocked, submitted_for_qa, qa_in_progress or passed_qa)
        $unfinishedTask = $this->getAssignedTask();
        $unfinishedTask->save();

        // Create new task, set as reserved
        $reservedTask = $this->getNewTask();
        $reservedTask->project_id = $project->id;
        $reservationsBy = [
            [
                'user_id' => $this->profile->id,
                'timestamp' => (new \DateTime())->format('U')
            ]
        ];
        $reservedTask->reservationsBy = $reservationsBy;
        $reservedTask->save();

        // Try to claim task and trigger event
        $reservedTask->owner = $this->profile->id;

        $event = new TaskClaim($reservedTask);
        $listener = new \App\Listeners\TaskClaim();

        $this->setExpectedException(
            UserInputException::class,
            'Permission denied. There are unfinished previous tasks.',
            400
        );
        $out = $listener->handle($event);
        $this->assertEquals($out, $this->getExpectedException());
    }

    /**
     * Test task claim listener to deny reservation because user not a member of project that task belongs to
     */
    public function testTaskClaimListenerDenyTaskReservationNotMemberOfProject()
    {
        // Create new project with empty members list
        $project = $this->getNewProject();
        $project->save();

        // Create new task, update with reservation and trigger event
        $taskToReserve = $this->getNewTask();
        $taskToReserve->project_id = $project->id;
        $taskToReserve->save();
        $reservationsBy = [
            [
                'user_id' => $this->profile->id,
                'timestamp' => (new \DateTime())->format('U')
            ]
        ];
        $taskToReserve->reservationsBy = $reservationsBy;

        $event = new TaskClaim($taskToReserve);
        $listener = new \App\Listeners\TaskClaim();

        $this->setExpectedException(
            UserInputException::class,
            'Permission denied. Not a member of project.',
            400
        );
        $out = $listener->handle($event);
        $this->assertEquals($out, $this->getExpectedException());
    }
}
