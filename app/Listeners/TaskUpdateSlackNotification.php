<?php

namespace App\Listeners;

use App\Events\TaskUpdateSlackNotify;
use App\GenericModel;
use App\Helpers\Slack;
use App\Profile;
use Illuminate\Support\Facades\Config;
use App\Helpers\AuthHelper;

class TaskUpdateSlackNotification
{
    /**
     * Handle the event.
     *
     * @param  TaskUpdateSlackNotify $event
     * @return void
     */
    public function handle(TaskUpdateSlackNotify $event)
    {
        $user = AuthHelper::getAuthenticatedUser();
        $task = $event->model;

        $project = GenericModel::findModel($task->project_id, 'projects');
        $projectOwner = GenericModel::findModel($project->acceptedBy, 'profiles');
        $taskOwner = GenericModel::findModel($task->owner, 'profiles');

        // Let's build a list of recipients
        $recipients = [];

        if (!isset($task->watchers)) {
            $task->watchers = [];
        }

        foreach ($task->watchers as $watcher) {
            $watcherProfile = GenericModel::findModel($watcher, 'profiles');
            if ($watcherProfile !== null && $watcherProfile->slack) {
                $recipients[] = '@' . $watcherProfile->slack;
            }
        }

        if ($projectOwner && $projectOwner->slack && $projectOwner->_id !== $user->_id) {
            $recipients[] = '@' . $projectOwner->slack;
        }

        if ($taskOwner && $taskOwner->slack && $taskOwner->_id !== $user->_id) {
            $recipients[] = '@' . $taskOwner->slack;
        }

        // Make sure that we don't double send notifications if task owner is project owner
        $recipients = array_unique($recipients);

        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
        $message = 'Task *'
            . $task->title
            . '* was just updated by *'
            . $user->name
            . '* '
            . $webDomain
            . 'projects/'
            . $task->project_id
            . '/sprints/'
            . $task->sprint_id
            . '/tasks/'
            . $task->_id;

        foreach ($recipients as $recipient) {
            Slack::sendMessage($recipient, $message, Slack::LOW_PRIORITY);
        }
    }
}
