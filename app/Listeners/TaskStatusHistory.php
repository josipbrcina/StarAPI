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

            //update task_history if task is paused or resumed without submitted for QA
            if (key_exists('paused', $newValues) && (!key_exists('submitted_for_qa', $newValues))) {
                $taskHistory[] = [
                    'user' => $taskOwner->_id,
                    'timestamp' => (int)($unixTime . '000'),
                    'event' => $newValues['paused'] === true ?
                        str_replace('%s', ' ', $taskHistoryStatuses['paused'])
                        : $taskHistoryStatuses['resumed'],
                    'status' => $newValues['paused'] === true ? 'paused' : 'resumed'
                ];
            }

            //update task_history if task is submitted for QA or if task fails QA
            if (key_exists('submitted_for_qa', $newValues)) {
                $taskHistory[] = [
                    'user' => $taskOwner->_id,
                    'timestamp' => (int)($unixTime . '000'),
                    'event' => $newValues['submitted_for_qa'] === true ?
                        $taskHistoryStatuses['qa_ready']
                        : $taskHistoryStatuses['qa_fail'],
                    'status' => $newValues['submitted_for_qa'] === true ? 'qa_ready' : 'qa_fail'
                ];
                //if task fails QA set task to paused and update task_history for pause
                if ($newValues['submitted_for_qa'] === false) {
                    $task->paused = true;
                    $taskHistory[] = [
                        'user' => $taskOwner->_id,
                        'timestamp' => (int)($unixTime . '000'),
                        'event' => str_replace('%s', 'Task failed QA', $taskHistoryStatuses['paused']),
                        'status' => 'paused'
                    ];
                }
            }

            //update task_history if task passed QA
            if (key_exists('passed_qa', $newValues) && $newValues['passed_qa'] === true) {
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
