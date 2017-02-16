<?php

namespace App\Listeners;

use App\GenericModel;

class TaskStatusTimeCalculation
{
    /**
     * Handle the event.
     * @param \App\Events\TaskStatusTimeCalculation $event
     * @return bool|GenericModel
     */
    public function handle(\App\Events\TaskStatusTimeCalculation $event)
    {
        $task = $event->model;
        $unixTime = (new \DateTime())->format('U');

        //if collection is other than tasks, return false
        if ($task['collection'] !== 'tasks') {
            return false;
        }

        $updatedFields = $task->getDirty();

        $taskOwner = $task->owner;

        if (empty($newTaskOwner) && array_key_exists('owner', $updatedFields)) {
            $taskOwner = $updatedFields['owner'];
        }

        if (empty($taskOwner)) {
            return false;
        }

        if (!isset($task->work)) {
            $task->work = [];
        }

        //on task creation check if there is owner assigned and set work field
        if ($task->exists === false) {
            $task->work = [
                $taskOwner => [
                    'worked' => 0,
                    'paused' => 0,
                    'qa' => 0,
                    'qa_in_progress' => 0,
                    'qa_total_time' => 0,
                    'blocked' => 0,
                    'workTrackTimestamp' => $unixTime,
                    'timeAssigned' => $unixTime
                ]
            ];
        } elseif (!isset($task->work[$taskOwner])) {
            $work = $task->work;
            $work[$taskOwner] = [
                'worked' => 0,
                'paused' => 0,
                'qa' => 0,
                'qa_in_progress' => 0,
                'qa_total_time' => 0,
                'blocked' => 0,
                'workTrackTimestamp' => $unixTime,
                'timeAssigned' => $unixTime
            ];
            $task->work = $work;
        }

        //handle task status time logic if model is updated and has got task owner
        if ($task->isDirty()) {
            //if task is reassigned, set properly work field values for all task owners
            //set work field for user that's first time on task(reassigned)
            if (key_exists('owner', $updatedFields)) {
                $work = $task->work;
                //update work stats list of old user owners
                foreach ($work as $ownerId => $workArray) {
                    if ($ownerId !== $updatedFields['owner'] && !key_exists('timeRemoved', $workArray)) {
                        //calculate times for last active task owner
                        $calculatedTime = (int)($unixTime - $work[$ownerId]['workTrackTimestamp']);

                        if ($task->paused !== true
                            && $task->blocked !== true
                            && $task->submitted_for_qa !== true
                            && $task->qa_in_progress !== true
                        ) {
                            $work[$ownerId]['worked'] += $calculatedTime;
                        }
                        if ($task->paused) {
                            $work[$ownerId]['paused'] += $calculatedTime;
                        }
                        if ($task->submitted_for_qa) {
                            $work[$ownerId]['qa'] += $calculatedTime;
                        }
                        if ($task->blocked) {
                            $work[$ownerId]['blocked'] += $calculatedTime;
                        }
                        if ($task->qa_in_progress) {
                            $work[$ownerId]['qa_in_progress'] += $calculatedTime;
                            $work[$ownerId]['qa_total_time'] += $calculatedTime;
                        }

                        $work[$ownerId]['timeRemoved'] = $unixTime;
                        $work[$ownerId]['workTrackTimestamp'] = $unixTime;
                    }
                    //if user is reassigned set time flags to assigned
                    if ($ownerId === $updatedFields['owner']) {
                        unset($work[$ownerId]['timeRemoved']);
                        $work[$ownerId]['workTrackTimestamp'] = $unixTime;
                        $work[$ownerId]['timeAssigned'] = $unixTime;
                    }
                }
                $task->work = $work;
            }

            //when task status is paused/resumed calculate time for worked/paused
            if (key_exists('paused', $updatedFields)
                && !key_exists('qa_in_progress', $updatedFields)
                && !key_exists('submitted_for_qa', $updatedFields)) {
                $work = $task->work;
                $calculatedTime = (int)($unixTime - $work[$task->owner]['workTrackTimestamp']);
                $updatedFields['paused'] === true ?
                    $work[$task->owner]['worked'] += $calculatedTime :
                    $work[$task->owner]['paused'] += $calculatedTime;
                $work[$task->owner]['workTrackTimestamp'] = $unixTime;

                $task->work = $work;
            }
            //when task status is blocked/unblocked calculate time for worked/blocked
            if (key_exists('blocked', $updatedFields)) {
                $work = $task->work;
                $calculatedTime = (int)($unixTime - $work[$task->owner]['workTrackTimestamp']);
                $updatedFields['blocked'] === true ?
                    $work[$task->owner]['worked'] += $calculatedTime :
                    $work[$task->owner]['blocked'] += $calculatedTime;
                $work[$task->owner]['workTrackTimestamp'] = $unixTime;

                $task->work = $work;
            }
            //when task status is submitted_for_qa calculate time for worked
            if (key_exists('submitted_for_qa', $updatedFields)
                && !key_exists('qa_in_progress', $updatedFields)
            ) {
                $work = $task->work;
                $calculatedTime = (int)($unixTime - $work[$task->owner]['workTrackTimestamp']);
                $updatedFields['submitted_for_qa'] === true ?
                    $work[$task->owner]['worked'] += $calculatedTime
                    : $work[$task->owner]['qa'] += $calculatedTime;

                $work[$task->owner]['workTrackTimestamp'] = $unixTime;

                $task->work = $work;
            }
            //when task status is set to failed QA calculate time for qa_in_progress
            if (key_exists('qa_in_progress', $updatedFields) && !key_exists('passed_qa', $updatedFields)) {
                $work = $task->work;
                $calculatedTime = (int)($unixTime - $work[$task->owner]['workTrackTimestamp']);
                if ($updatedFields['qa_in_progress'] === true) {
                    $work[$task->owner]['qa'] += $calculatedTime;
                } else {
                    $work[$task->owner]['qa_in_progress'] = 0;
                    $work[$task->owner]['qa_total_time'] += $calculatedTime;
                }
                $work[$task->owner]['workTrackTimestamp'] = $unixTime;

                $task->work = $work;
            }
            //when task status is passed_qa update task work timestamp
            if (key_exists('passed_qa', $updatedFields)
                && $updatedFields['passed_qa'] === true
                && key_exists('qa_in_progress', $updatedFields)
                && $updatedFields['qa_in_progress'] === false
            ) {
                $work = $task->work;
                $calculatedTime = (int)($unixTime - $work[$task->owner]['workTrackTimestamp']);
                $work[$task->owner]['qa_in_progress'] += $calculatedTime;
                $work[$task->owner]['qa_total_time'] += $calculatedTime;
                $work[$task->owner]['workTrackTimestamp'] = $unixTime;

                $task->work = $work;
            }
        }

        return $task;
    }
}
