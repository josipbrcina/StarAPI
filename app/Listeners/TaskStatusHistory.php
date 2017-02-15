<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Config;
use App\Profile;

class TaskStatusHistory
{

    /**
     * Handle the event.
     * @param \App\Events\TaskStatusHistory $event
     */
    public function handle(\App\Events\TaskStatusHistory $event)
    {
        $task = $event->model;

        if ($task['collection'] === 'tasks' && $task->isDirty()) {
            $newValues = $event->model->getDirty();
            $taskHistory = $event->model->task_history;
            $taskHistoryStatuses = Config::get('sharedSettings.internalConfiguration.taskHistoryStatuses');
            $date = new \DateTime();
            $unixTime = $date->format('U');
            $taskOwner = Profile::find($task->owner);

            //update task_history if task is claimed or assigned
            if (key_exists('owner', $newValues)) {
                $taskOwner = Profile::find($newValues['owner']);
                $taskHistory[] = [
                    'user' => $taskOwner->_id,
                    'timestamp' => (int)($unixTime . '000'),
                    'event' => str_replace('%s', $taskOwner->name, $taskOwner->_id === \Auth::user()->id ?
                        $taskHistoryStatuses['claimed']
                        : $taskHistoryStatuses['assigned']),
                    'status' => $taskOwner->_id === \Auth::user()->id ? 'claimed' : 'assigned'
                ];
            }

            //update task_history if task is paused or resumed without QA in progress
            if (key_exists('paused', $newValues) && (!key_exists('qa_progress', $newValues))) {
                $taskHistory[] = [
                    'user' => $taskOwner->_id,
                    'timestamp' => (int)($unixTime . '000'),
                    'event' => $newValues['paused'] === true ?
                        str_replace('%s', ' ', $taskHistoryStatuses['paused'])
                        : $taskHistoryStatuses['resumed'],
                    'status' => $newValues['paused'] === true ? 'paused' : 'resumed'
                ];
            }

            //update task_history if task is submitted for QA
            if (key_exists('submitted_for_qa', $newValues) && $newValues['submitted_for_qa'] === true) {
                $taskHistory[] = [
                    'user' => $taskOwner->_id,
                    'timestamp' => (int)($unixTime . '000'),
                    'event' => $taskHistoryStatuses['qa_ready'],
                    'status' => 'qa_ready'
                ];
            }

            //update task_history if task is set to QA in progress
            if (key_exists('qa_progress', $newValues) && $newValues['qa_progress'] === true) {
                $task->submitted_for_qa = false;
                $taskHistory[] = [
                    'user' => $taskOwner->_id,
                    'timestamp' => (int)($unixTime . '000'),
                    'event' => $taskHistoryStatuses['qa_progress'],
                    'status' => 'qa_progress'
                ];

            }

            //if task fails QA set task to paused and update task_history for paused
            if (key_exists('qa_progress', $newValues) && $newValues['qa_progress'] === false) {
                $task->paused = true;
                $taskHistory[] = [
                    'user' => $taskOwner->_id,
                    'timestamp' => (int)($unixTime . '000'),
                    'event' => str_replace('%s', 'Task failed QA', $taskHistoryStatuses['paused']),
                    'status' => 'paused'
                ];
            }

            //update task_history if task passed QA
            if (key_exists('passed_qa', $newValues) && $newValues['passed_qa'] === true) {
                $task->qa_progress = false;
                $taskHistory[] = [
                    'user' => $taskOwner->_id,
                    'timestamp' => (int)($unixTime . '000'),
                    'event' => $taskHistoryStatuses['qa_success'],
                    'status' => 'qa_success'
                ];
            }

            $task->task_history = $taskHistory;
        }
    }
}
