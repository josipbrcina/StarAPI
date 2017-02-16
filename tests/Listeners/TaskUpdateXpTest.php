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
}
