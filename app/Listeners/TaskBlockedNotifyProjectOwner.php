<?php

namespace App\Listeners;

use App\GenericModel;
use App\Profile;
use App\Helpers\Slack;
use Illuminate\Support\Facades\Config;

/**
 * Class TaskBlockedNotifyProjectOwner
 * @package App\Listeners
 */
class TaskBlockedNotifyProjectOwner
{
    /**
     * Handle the event.
     * @param \App\Events\TaskBlockedNotifyProjectOwner $event
     * @return bool
     */
    public function handle(\App\Events\TaskBlockedNotifyProjectOwner $event)
    {
        $task = $event->model;

        if ($task->isDirty() && $task['collection'] === 'tasks') {
            $updatedFields = $task->getDirty();

            if (key_exists('blocked', $updatedFields) && $updatedFields['blocked'] === true) {
                $preSetCollection = GenericModel::getCollection();
                GenericModel::setCollection('projects');
                $project = GenericModel::find($task->project_id);
                GenericModel::setCollection($preSetCollection);

                // Get project owner and send slack message that task is blocked
                $po = Profile::find($project->acceptedBy);
                if ($po && $po->slack) {
                    $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
                    $recipient = '@' . $po->slack;
                    $message = 'Hey, task *'
                        . $task->title
                        . '* is currently blocked! '
                        . $webDomain
                        . 'projects/'
                        . $task->project_id
                        . '/sprints/'
                        . $task->sprint_id
                        . '/tasks/'
                        . $task->_id;
                    Slack::sendMessage($recipient, $message, Slack::HIGH_PRIORITY);

                    return true;
                }

                return false;
            }
        }
    }
}
