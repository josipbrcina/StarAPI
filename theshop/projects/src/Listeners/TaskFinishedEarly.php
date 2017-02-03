<?php

namespace TheShop\Projects\Listeners;

use App\GenericModel;
use App\Services\ProfilePerformance;
use App\Profile;
use App\Helpers\InputHandler;
use Illuminate\Support\Facades\Config;

class TaskFinishedEarly
{
    /**
     * Handle the event.
     * @param \TheShop\Projects\Events\TaskFinishedEarly $event
     */
    public function handle(\TheShop\Projects\Events\TaskFinishedEarly $event)
    {
        $task = $event->model;

        if ($task->isDirty()) {
            $updatedFields = $task->getDirty();

            if ($task['collection'] === 'tasks' && key_exists('passed_qa', $updatedFields)) {
                $profilePerformance = new ProfilePerformance();
                $preSetCollection = GenericModel::getCollection();
                GenericModel::setCollection('tasks');
                $taskPerformance = $profilePerformance->perTask($task);
                foreach ($taskPerformance as $profileId => $taskDetails) {
                    $taskOwnerProfile = Profile::find($profileId);
                    $mappedValues = $profilePerformance->getTaskValuesForProfile($taskOwnerProfile, $task);

                    //calculate estimated time, working time, and 60% of estimated
                    $estimatedSeconds = max(InputHandler::getInteger($mappedValues['estimatedHours']) * 60 * 60, 1);
                    $secondsWorking = $taskDetails['workSeconds'];
                    $sixtyPercentOfEstimate = 0.6 * $estimatedSeconds;

                    //if tasked finished in less than 60% of time send slack notification to admins
                    if ($secondsWorking <= $sixtyPercentOfEstimate) {
                        $admins = Profile::where('admin', '=', true)->get();
                        foreach ($admins as $admin) {
                            $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
                            $recipient = '@' . $admin->slack;
                            $message = 'Hey, task *'
                                . $task->title
                                . '* wrapped up in less than *60%* of estimated time. '
                                . $webDomain
                                . 'projects/'
                                . $task->project_id
                                . '/sprints/'
                                . $task->sprint_id
                                . '/tasks/'
                                . $task->_id;
                            \SlackChat::message($recipient, $message);
                        }
                    }
                }
                GenericModel::setCollection($preSetCollection);
            }
        }
    }
}
