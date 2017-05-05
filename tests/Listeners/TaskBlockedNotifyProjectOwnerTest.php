<?php

namespace Tests\Listeners;

use App\GenericModel;
use App\Listeners\TaskBlockedNotifyProjectOwner;
use Tests\TestCase;
use Tests\Collections\ProjectRelated;
use Tests\Collections\ProfileRelated;
use App\Profile;

/**
 * Class TaskBlockedNotifyProjectOwnerTest
 * @package Tests\Listeners
 */
class TaskBlockedNotifyProjectOwnerTest extends TestCase
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

    /**
     * Test listener to send slack message to PO when task is blocked with HIGH priority
     */
    public function testTaskBlockedNotifyProjectOwnerListenerSendMessage()
    {
        // Get new project
        $project = $this->getNewProject();
        $project->acceptedBy = $this->profile->id;
        $project->save();

        // Get new sprint
        $sprint = $this->getNewSprint();
        $sprint->project_id = $project->id;
        $sprint->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $task = $this->getAssignedTask($assignedAgo);
        $task->title = 'Test';
        $task->project_id = $project->id;
        $task->sprint_id = $sprint->id;
        $task->save();

        // Set profile slack
        $this->profile->slack = 'slacktest';
        $this->profile->save();

        // Call event and listener
        $task->blocked = true;
        $event = new \App\Events\TaskBlockedNotifyProjectOwner($task);
        $listener = new TaskBlockedNotifyProjectOwner($task);
        $response = $listener->handle($event);


        // Get message from slackMessages collection
        $recipient = '@' . $this->profile->slack;
        $messageRecord = GenericModel::whereTo('slackMessages')
            ->where('recipient', '=', $recipient)
            ->orderBy('_id', 'desc')
            ->first();

        $this->assertEquals(true, $response);
        $this->assertEquals($messageRecord->recipient, $recipient);
        $this->assertEquals(
            'Hey, task *'
            . $task->title
            . '* is currently blocked! http://the-shop.io:3000/projects/'
            . $project->id
            . '/sprints/'
            . $sprint->id
            . '/tasks/'
            . $task->id,
            $messageRecord->message
        );
        $this->assertEquals(0, $messageRecord->priority);
        $this->assertEquals(false, $messageRecord->sent);
    }

    /**
     * Test listener for task blocked without project owner set
     */
    public function testTaskBlockedNotifyProjectOwnerListenerWithoutPo()
    {
        // Get new project
        $project = $this->getNewProject();
        $project->save();

        // Assigned 30 minutes ago
        $minutesWorking = 30;
        $assignedAgo = (int)(new \DateTime())->sub(new \DateInterval('PT' . $minutesWorking . 'M'))->format('U');

        $task = $this->getAssignedTask($assignedAgo);
        $task->title = 'Test';
        $task->project_id = $project->id;
        $task->save();

        // Call event and listener
        $task->blocked = true;
        $event = new \App\Events\TaskBlockedNotifyProjectOwner($task);
        $listener = new TaskBlockedNotifyProjectOwner($task);
        $response = $listener->handle($event);

        $this->assertEquals(false, $response);
    }
}
