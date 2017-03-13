<?php

namespace App\Console\Commands;

use App\GenericModel;
use App\Helpers\InputHandler;
use App\Helpers\Slack;
use App\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

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

        // Unix timestamp 7 days from now at the end of the day
        $unixSevenDaysFromNow = (int) Carbon::now()->addDays(7)->format('U')
            + (int)Carbon::now()->addDays(7)->secondsUntilEndOfDay();

        // Get all unfinished tasks with due_date within next 7 days
        $tasks = GenericModel::where('due_date', '<=', $unixSevenDaysFromNow)
            ->where('passed_qa', '=', false)
            ->get();

        $date2DaysFromNow = Carbon::now()->addDays(2)->format('Y-m-d');

        foreach ($tasks as $task) {
            $taskDueDate = Carbon::createFromFormat('U', InputHandler::getUnixTimestamp($task->due_date))
                ->format('Y-m-d');

            $recipients = [];

            // Get all project members of project that task belongs to
            GenericModel::setCollection('projects');
            $taskProject = GenericModel::where('_id', '=', $task->project_id)->first();
            foreach ($taskProject->members as $memberId) {
                $memberProfile = Profile::find($memberId);
                $compareSkills = array_intersect($memberProfile->skills, $task->skillset);
                if ($memberProfile !== null
                    && $memberProfile->slack
                    && !empty($compareSkills)
                    && $memberProfile->active
                ) {
                    $recipients[] = '@' . $memberProfile->slack;
                }
            }

            // Get all admins and project owner
            $adminsAndPo = Profile::where('admin', '=', true)
                ->orWhere('_id', '=', $taskProject->acceptedBy)
                ->get();

            foreach ($adminsAndPo as $adminOrPo) {
                if ($adminOrPo->slack && $adminOrPo->active) {
                    $recipients[] = '@' . $adminOrPo->slack;
                }
            }

            // Make sure that we don't double send notifications if project member is admin or project owner
            $recipients = array_unique($recipients);

            // Create slack message
            $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
            $deadlineMessage = $taskDueDate <= $date2DaysFromNow ?
                '* deadline is in next *2 days* '
                : '* deadline is in next *3-7 days* ';
            $message = 'Hey, task *'
                . $task->title
                . '* on project *'
                . $taskProject->name
                . $deadlineMessage
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
