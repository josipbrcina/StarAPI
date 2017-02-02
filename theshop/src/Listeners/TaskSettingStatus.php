<?php

namespace TheShop\Src\Listeners;

use App\Exceptions\UserInputException;

class TaskSettingStatus
{
    /**
     * Handle the event
     * @param \TheShop\Src\Events\TaskSettingStatus $event
     * @throws UserInputException
     */
    public function handle(\TheShop\Src\Events\TaskSettingStatus $event)
    {
        $task = $event->model;

        //if task is not claimed by user, deny task setting status to be changed
        if ($task->isDirty()) {
            $updatedFields = $task->getDirty();
            $keysToCheck = ['paused', 'submitted_for_qa', 'passed_qa'];
            $checked = array_intersect_key($updatedFields, array_flip($keysToCheck));
            if ($task['collection'] === 'tasks' && empty($task->owner) && count($checked) > 0) {
                throw new UserInputException('Permission denied. Task is not claimed.');
            }
        }
    }
}
