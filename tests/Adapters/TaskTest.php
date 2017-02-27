<?php

namespace Tests\Adapters;

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
}
