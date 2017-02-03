<?php

namespace TheShop\Projects\Listeners;

use App\Exceptions\UserInputException;
use App\GenericModel;

class TaskClaim
{
    /**
     * Handle the event.
     * @param \TheShop\Projects\Events\TaskClaim $event
     * @throws UserInputException
     */
    public function handle(\TheShop\Projects\Events\TaskClaim $event)
    {
        $task = $event->model;

        if ($task->isDirty()) {
            $preSetCollection = GenericModel::getCollection();
            $updatedFields = $task->getDirty();
            if ($task['collection'] === 'tasks' && key_exists('owner', $updatedFields)) {
                GenericModel::setCollection('tasks');
                $allUserTasks = GenericModel::where('_id', '!=', $task->_id)
                    ->where('owner', '=', $updatedFields['owner'])
                    ->get();
                foreach ($allUserTasks as $item) {
                    if ($item->passed_qa === true || $item->submitted_for_qa === true) {
                        continue;
                    }
                    throw new UserInputException('Permission denied. There are unfinished previous tasks.');
                }
            }

            GenericModel::setCollection($preSetCollection);
        }
    }
}
