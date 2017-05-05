<?php

namespace App\Listeners;

use App\Exceptions\UserInputException;
use App\GenericModel;
use Illuminate\Support\Facades\Config;

class TaskClaim
{
    /**
     * Handle the event.
     * @param \App\Events\TaskClaim $event
     * @return bool
     * @throws UserInputException
     */
    public function handle(\App\Events\TaskClaim $event)
    {
        $task = $event->model;

        if ($task->isDirty()) {
            $updatedFields = $task->getDirty();
            if ($task['collection'] === 'tasks'
                && (key_exists('owner', $updatedFields) || key_exists('reservationsBy', $updatedFields))
            ) {
                $allTasks = GenericModel::whereTo('tasks')
                    ->where('_id', '!=', $task->_id)
                    ->get();
                $taskReservationTime =
                    Config::get('sharedSettings.internalConfiguration.tasks.reservation.maxReservationTime');
                $currentUnixTime = (new \DateTime())->format('U');

                // Check if task is claimed/assigned and set owner ID
                if (key_exists('owner', $updatedFields)) {
                    $taskOwnerId = $updatedFields['owner'];
                }
                // Check if task is reserved and read user id from reservationsBy array
                if (key_exists('reservationsBy', $updatedFields)) {
                    $taskOwnerId = $updatedFields['reservationsBy'][0]['user_id'];
                }
                // Check if user is a member of project that task belongs to
                $project = GenericModel::whereTo('projects')
                    ->find($task->project_id);

                if (!in_array($taskOwnerId, $project->members)) {
                    throw new UserInputException('Permission denied. Not a member of project.', 403);
                }

                foreach ($allTasks as $item) {
                    // Check if user already has some task reserved within reservation time
                    if (!empty($item->reservationsBy)) {
                        foreach ($item->reservationsBy as $userReservation) {
                            if ($currentUnixTime - $userReservation['timestamp'] <= ($taskReservationTime * 60)
                                && $userReservation['user_id'] === $taskOwnerId
                            ) {
                                throw new UserInputException('Permission denied. There is reserved previous task.', 403);
                            }
                        }
                    }
                    // Check if user has got some unfinished tasks
                    if ($item->owner === $taskOwnerId
                        && $item->passed_qa === false
                        && $item->blocked === false
                        && $item->qa_in_progress === false
                        && $item->submitted_for_qa === false
                    ) {
                        throw new UserInputException('Permission denied. There are unfinished previous tasks.', 403);
                    }
                }
            }

            return true;
        }
    }
}
