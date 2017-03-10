<?php

namespace App\Console\Commands;

use App\GenericModel;
use App\Helpers\InputHandler;
use App\Helpers\Slack;
use App\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use DateTime;

/**
 * Class NotifyProjectParticipantsAboutTaskDeadline
 * @package App\Console\Commands
 */
class NotifyProjectParticipantsAboutTaskDeadline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ping:projectParticipants:task:deadline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description =
        'Ping admins,project members and project owner on slack about approaching task deadline 7 days before deadline';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        GenericModel::setCollection('tasks');
        $tasks = GenericModel::all();

        $unixDateNow = (new DateTime())->format('U');
        $unixSevenDaysFromNow = (int) ($unixDateNow) + 24 * 7 * 60 * 60;
        $sevenDaysFromNowDate = DateTime::createFromFormat('U', $unixSevenDaysFromNow)->format('Y-m-d');
        foreach ($tasks as $task) {
            if (!empty($task->due_date) && $sevenDaysFromNowDate
                === DateTime::createFromFormat('U', InputHandler::getUnixTimestamp($task->due_date))->format('Y-m-d')
            ) {
                $recipients = [];
                // Get all project members of project that task belongs to
                GenericModel::setCollection('projects');
                $taskProject = GenericModel::where('_id', '=', $task->project_id)->first();
                foreach ($taskProject->members as $memberId) {
                    $memberProfile = Profile::find($memberId);
                    if ($memberProfile !== null && $memberProfile->slack) {
                        $recipients[] = '@' . $memberProfile->slack;
                    }
                }

                // Get all admins and project owner
                $adminsAndPo = Profile::where('admin', '=', true)
                    ->orWhere('_id', '=', $taskProject->acceptedBy)
                    ->get();

                foreach ($adminsAndPo as $adminOrPo) {
                    if ($adminOrPo->slack) {
                        $recipients[] = '@' . $adminOrPo->slack;
                    }
                }

                // Make sure that we don't double send notifications if project member is admin or project owner
                $recipients = array_unique($recipients);

                // Create slack message
                $taskDueDate = DateTime::createFromFormat('U', InputHandler::getUnixTimestamp($task->due_date))
                    ->format('Y-m-d');
                $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
                $message = 'Hey, task *'
                    . $task->title
                    . '* on project *'
                    . $taskProject->name
                    . '* deadline is in *7 days* '
                    . '(*'
                    . $taskDueDate
                    . '*) '
                    . $webDomain
                    . 'projects/'
                    . $task->project_id
                    . '/sprints/'
                    . $task->sprint_id
                    . '/tasks/'
                    . $task->_id;
                // Send messages to all recipients
                foreach ($recipients as $recipient) {
                    Slack::sendMessage($recipient, $message, Slack::LOW_PRIORITY);
                }
            }
        }
    }
}
