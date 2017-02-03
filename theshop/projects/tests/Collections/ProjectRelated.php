<?php

namespace TheShop\Tests\Collections;

use App\GenericModel;
use App\Profile;

trait ProjectRelated
{
    protected $profile = null;

    public function setTaskOwner(Profile $owner)
    {
        $this->profile = $owner;
    }

    public function getTaskWithEmptyHistory()
    {
        GenericModel::setCollection('tasks');
        return new GenericModel(
            [
                'owner' => $this->profile->id,
                'task_history' => [],
            ]
        );
    }

    public function getTaskWithJustClaimedHistory($timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        $task = $this->getTaskWithEmptyHistory();

        $task->task_history = [
            [
                'event' => 'Task claimed by sample user',
                'status' => 'claimed',
                'user' => $this->profile->id,
                'timestamp' => $timestamp,
            ]
        ];

        return $task;
    }

    public function getTaskWithJustAssignedHistory($timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        $task = $this->getTaskWithEmptyHistory();

        $task->task_history = [
            [
                'event' => 'Task assigned to sample user',
                'status' => 'assigned',
                'user' => $this->profile->id,
                'timestamp' => $timestamp,
            ]
        ];

        return $task;
    }

    public function getAssignedAndPausedTask($timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        $task = $this->getTaskWithJustAssignedHistory($timestamp - 5);

        $th = $task->task_history;

        $th[] = [
            'event' => 'Task paused because of: "testing pause"',
            'status' => 'paused',
            'user' => $this->profile->id,
            'timestamp' => $timestamp,
        ];

        $task->task_history = $th;

        return $task;
    }

    public function getResumedTask($timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        $task = $this->getAssignedAndPausedTask($timestamp - 5);

        $th = $task->task_history;

        $th[] = [
            'event' => 'Task resumed',
            'status' => 'resumed',
            'user' => $this->profile->id,
            'timestamp' => $timestamp,
        ];

        $task->task_history = $th;

        return $task;
    }

    public function getQaSubmittedTask($timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        $task = $this->getResumedTask($timestamp - 5);

        $th = $task->task_history;

        $th[] = [
            'event' => 'Task ready for QA',
            'status' => 'qa_ready',
            'user' => $this->profile->id,
            'timestamp' => $timestamp,
        ];

        $task->task_history = $th;

        return $task;
    }

    public function getQaFailTask($timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        $task = $this->getQaSubmittedTask($timestamp - 15);

        $th = $task->task_history;

        $th[] = [
            'event' => 'Task failed QA',
            'status' => 'qa_fail',
            'user' => $this->profile->id,
            'timestamp' => $timestamp - 10,
        ];

        $th[] = [
            'event' => 'Task paused because of "qa fail"',
            'status' => 'paused',
            'user' => $this->profile->id,
            'timestamp' => $timestamp - 5,
        ];

        $th[] = [
            'event' => 'Task resumed',
            'status' => 'resumed',
            'user' => $this->profile->id,
            'timestamp' => $timestamp,
        ];

        $task->task_history = $th;

        return $task;
    }

    public function getMultipleQaFailTask($timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        $task = $this->getQaFailTask($timestamp - 20);

        $th = $task->task_history;

        $th[] = [
            'event' => 'Task ready for QA',
            'status' => 'qa_ready',
            'user' => $this->profile->id,
            'timestamp' => $timestamp - 15,
        ];

        $th[] = [
            'event' => 'Task failed QA',
            'status' => 'qa_fail',
            'user' => $this->profile->id,
            'timestamp' => $timestamp - 10, // Failed 5 seconds later
        ];

        $th[] = [
            'event' => 'Task paused because of "qa fail"',
            'status' => 'paused',
            'user' => $this->profile->id,
            'timestamp' => $timestamp - 5,
        ];

        $th[] = [
            'event' => 'Task resumed',
            'status' => 'resumed',
            'user' => $this->profile->id,
            'timestamp' => $timestamp,
        ];

        $task->task_history = $th;

        return $task;
    }

    public function getQaPassedTask($timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        $task = $this->getMultipleQaFailTask($timestamp - 10);

        $th = $task->task_history;

        $th[] = [
            'event' => 'Task ready for QA',
            'status' => 'qa_ready',
            'user' => $this->profile->id,
            'timestamp' => $timestamp - 5,
        ];

        $th[] = [
            'event' => 'Task passed QA',
            'status' => 'qa_success',
            'user' => $this->profile->id,
            'timestamp' => $timestamp,
        ];

        $task->task_history = $th;

        return $task;
    }
}
