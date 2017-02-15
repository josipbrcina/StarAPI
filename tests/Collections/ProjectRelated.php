<?php

namespace Tests\Collections;

use App\GenericModel;
use App\Profile;

trait ProjectRelated
{
    protected $profile = null;

    public function setTaskOwner(Profile $owner)
    {
        $this->profile = $owner;
    }

    /*
    |--------------------------------------------------------------------------
    | Get methods
    |--------------------------------------------------------------------------
    |
    | Here are getter methods for tests related to projects(tasks,user XP, profile performance etc.)
    */

    /**
     * Get new task without owner
     * @return GenericModel
     */
    public function getNewTask()
    {
        GenericModel::setCollection('tasks');
        return new GenericModel(
            [
                'owner' => '',
                'paused' => false,
                'submitted_for_qa' => false,
                'blocked' => false,
                'passed_qa' => false
            ]
        );
    }

    /**
     * Get new project
     * @return GenericModel
     */
    public function getNewProject()
    {
        GenericModel::setCollection('projects');
        return new GenericModel(
            [
                'owner' => '',
                'paused' => false,
                'submitted_for_qa' => false,
                'blocked' => false,
                'passed_qa' => false
            ]
        );
    }

    /**
     * Get assigned task
     * @return GenericModel
     */
    public function getAssignedTask($timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        GenericModel::setCollection('tasks');
        $task = $this->getNewTask();

        $task->owner = $this->profile->id;
        $task->work = [
            $this->profile->id => [
                'worked' => 0,
                'paused' => 0,
                'qa' => 0,
                'qa_in_progress' => 0,
                'qa_total_time' => 0,
                'blocked' => 0,
                'workTrackTimestamp' => $timestamp,
                'timeAssigned' => $timestamp
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

        $unixNow = (int)(new \DateTime())->format('U');

        $task = $this->getAssignedTask($timestamp);

        $task->work = [
            $this->profile->id => [
                'worked' => $unixNow - $timestamp,
                'paused' => 0,
                'qa' => 0,
                'qa_in_progress' => 0,
                'qa_total_time' => 0,
                'blocked' => 0,
                'workTrackTimestamp' => (int)(new \DateTime())->format('U')
            ]
        ];
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
}
