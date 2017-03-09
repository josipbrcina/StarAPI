<?php

namespace Tests\Adapters;

use Carbon\Carbon;
use Tests\Collections\ProfileRelated;
use Tests\Collections\ProjectRelated;
use Tests\TestCase;
use App\Profile;

/**
 * Class Task
 * @package Tests\Adapters
 */
class TaskTest extends TestCase
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

    /**
     * Test task status blocked colorIndicator on task adapter
     */
    public function testTaskAdapterColorIndicatorForBlocked()
    {
        $task = $this->getAssignedTask();
        $task->blocked = true;
        $task->save();

        $taskAdapter = new \App\Adapters\Task($task);
        $out = $taskAdapter->process();

        $this->assertEquals('brown', $out->colorIndicator);
    }

    /**
     *  Test task status qa_in_progress colorIndicator on task adapter
     */
    public function testTaskAdapterColorIndicatorForQaInProgress()
    {
        $task = $this->getAssignedTask();
        $task->qa_in_progress = true;
        $task->save();

        $taskAdapter = new \App\Adapters\Task($task);
        $out = $taskAdapter->process();

        $this->assertEquals('dark_green', $out->colorIndicator);
    }

    /**
     * Test task adapter colorIndicator red
     */
    public function testTaskAdapterColorIndicatorForTaskDueDateInNext2Days()
    {
        // Set task with due_date today
        $task = $this->getAssignedTask();
        $task->due_date = Carbon::now()->format('U');
        $task->save();

        $taskAdapter = new \App\Adapters\Task($task);
        $out = $taskAdapter->process();

        $this->assertEquals('red', $out->colorIndicator);

        // Set task with due_date 2 days from today
        $secondTask = $this->getAssignedTask();
        $task->due_date = Carbon::now()->addDays(2)->format('U');
        $task->save();

        $taskAdapter = new \App\Adapters\Task($secondTask);
        $out = $taskAdapter->process();

        $this->assertEquals('red', $out->colorIndicator);
    }

    /**
     * Test task adapter colorIndicator for task due_date within next 3-7 days
     */
    public function testTaskAdapterColorIndicatorForTaskDueDateWithinNext3to7Days()
    {
        // Set task with due_date 3 days from today
        $task = $this->getAssignedTask();
        $task->due_date = Carbon::now()->addDays(3)->format('U');
        $task->save();

        $taskAdapter = new \App\Adapters\Task($task);
        $out = $taskAdapter->process();

        $this->assertEquals('orange', $out->colorIndicator);

        // Set task with due_date 6 days from today
        $task = $this->getAssignedTask();
        $task->due_date = Carbon::now()->addDays(6)->format('U');
        $task->save();

        $taskAdapter = new \App\Adapters\Task($task);
        $out = $taskAdapter->process();

        $this->assertEquals('orange', $out->colorIndicator);
    }

    public function testTaskAdapterColorIndicatorForTaskDueDateMoreThan7DaysFromNow()
    {
        // Set task with due_date 10 days from today
        $task = $this->getAssignedTask();
        $task->due_date = Carbon::now()->addDays(10)->format('U');
        $task->save();

        $taskAdapter = new \App\Adapters\Task($task);
        $out = $taskAdapter->process();

        $this->assertEmpty($out->colorIndicator);
    }
}
