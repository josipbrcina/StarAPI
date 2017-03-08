<?php

namespace App\Listeners;

use App\Events\TaskUpdateSlackNotify;
use App\GenericModel;
use App\Helpers\Slack;
use App\Profile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

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
        $task = $event->model;
        $preSetCollection = GenericModel::getCollection();

        GenericModel::setCollection('projects');
        $project = GenericModel::find($task->project_id);

        $projectOwner = Profile::find($project->acceptedBy);
        $taskOwner = Profile::find($task->owner);


        // Let's build a list of recipients
        $recipients = [];

        foreach ($task->watchers as $watcher) {
            $watcherProfile = Profile::find($watcher);
            if ($watcherProfile !== null && $watcherProfile->slack) {
                $recipients[] = '@' . $watcherProfile->slack;
            }
        }

        if ($projectOwner && $projectOwner->slack && $projectOwner->_id !== Auth::user()->_id) {
            $recipients[] = '@' . $projectOwner->slack;
        }

        if ($taskOwner && $taskOwner->slack && $taskOwner->_id !== Auth::user()->_id) {
            $recipients[] = '@' . $taskOwner->slack;
        }

        // Make sure that we don't double send notifications if task owner is project owner
        $recipients = array_unique($recipients);

        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
        $message = 'Task *'
            . $task->title
            . '* was just updated by *'
            . Auth::user()->name
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

        GenericModel::setCollection($preSetCollection);
    }
}
