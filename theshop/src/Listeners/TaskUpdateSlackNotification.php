<?php

namespace TheShop\Src\Listeners;

use TheShop\Src\Events\TaskUpdateSlackNotify;
use App\GenericModel;
use Illuminate\Support\Facades\Auth;

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
        $preSetCollection = GenericModel::getCollection();
        GenericModel::setCollection('projects');
        $project = GenericModel::find($event->model->project_id);

        GenericModel::setCollection('profiles');
        $projectOwner = GenericModel::find($project->acceptedBy);
        $taskOwner = GenericModel::find($event->model->owner);

        // Let's build a list of recipients
        $recipients = [];

        if ($projectOwner && $projectOwner->slack && $projectOwner->_id !== Auth::user()->_id) {
            $recipients[] = '@' . $projectOwner->slack;
        }

        if ($taskOwner && $taskOwner->slack && $taskOwner->_id !== Auth::user()->_id) {
            $recipients[] = '@' . $taskOwner->slack;
        }

        // Make sure that we don't double send notifications if task owner is project owner
        $recipients = array_unique($recipients);

        $webDomain = \Config::get('sharedSettings.internalConfiguration.webDomain');
        $message = 'Task *'
            . $event->model->title
            . '* was just updated by *'
            . \Auth::user()->name
            . '* '
            . $webDomain
            . 'projects/'
            . $event->model->project_id
            . '/sprints/'
            . $event->model->sprint_id
            . '/tasks/'
            . $event->model->_id;

        foreach ($recipients as $recipient) {
            \SlackChat::message($recipient, $message);
        }

        GenericModel::setCollection($preSetCollection);
    }
}
