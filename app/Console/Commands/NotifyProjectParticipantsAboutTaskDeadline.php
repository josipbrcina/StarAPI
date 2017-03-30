<?php

namespace App\Console\Commands;

use App\GenericModel;
use App\Helpers\Slack;
use App\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use Vluzrmos\SlackApi\Facades\SlackChat;

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
        'Ping admins,project members and project owner on slack about task deadlines within next 7 days';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $unixNow = (int)Carbon::now()->format('U');
        // Unix timestamp 1 day before now at the beginning of the fay
        $unixYesterday = (int)Carbon::now()->subDay(1)->startOfDay()->format('U');
        // Unix timestamp 7 days from now at the end of the day
        $unixSevenDaysFromNow = (int)Carbon::now()->addDays(7)->endOfDay()->format('U');

        // Get all unfinished tasks with due_date between yesterday and next 7 days
        GenericModel::setCollection('tasks');
        $tasks = GenericModel::where('due_date', '<=', $unixSevenDaysFromNow)
            ->where('due_date', '>=', $unixYesterday)
            ->where('ready', '=', true)
            ->where('passed_qa', '=', false)
            ->get();

        $projects = [];
        $tasksDueDatePassed = [];
        $tasksDueDateIn7Days = [];

        foreach ($tasks as $task) {
            if (!array_key_exists($task->project_id, $projects)) {
                GenericModel::setCollection('projects');
                $project = GenericModel::find($task->project_id);
                if ($project) {
                    $projects[$project->_id] = $project;
                }
            }
            if ($task->due_date <= $unixNow) {
                $tasksDueDatePassed[$task->due_date][] = $task;
            } else {
                $tasksDueDateIn7Days[$task->due_date][] = $task;
            }
        }

        // Sort array of tasks ascending by due_date so we can notify about deadline
        ksort($tasksDueDateIn7Days);
        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');

        $profiles = Profile::where('active', '=', true)
            ->get();
        foreach ($profiles as $recipient) {
            if ($recipient->slack) {
                $recipientSlack = '@' . $recipient->slack;
                $sendDueDatesMessage = false;
                $sendDeadlinePassedMessage = false;

                /*Loop through tasks that have due_date within next 7 days, compare skills with recipient skills and get
                max 3 tasks with nearest due_date*/
                $tasksToNotifyRecipient = [];
                foreach ($tasksDueDateIn7Days as $tasksToNotifyArray) {
                    foreach ($tasksToNotifyArray as $taskToNotify) {
                        if (!$recipient->admin
                            && $recipient->id !== $projects[$taskToNotify->project_id]->acceptedBy
                            && !in_array($recipient->id, $projects[$taskToNotify->project_id]->members)
                        ) {
                            continue;
                        }
                        $compareSkills = array_intersect($recipient->skills, $taskToNotify->skillset);
                        if (!empty($compareSkills) && count($tasksToNotifyRecipient) < 3) {
                            $tasksToNotifyRecipient[] = $taskToNotify;
                        }
                    }
                }
                if (!empty($tasksToNotifyRecipient)) {
                    $sendDueDatesMessage = true;
                }

                // Create message for tasks with due_date within next 7 days
                $message = 'Hey, these tasks *due_date soon*:';
                foreach ($tasksToNotifyRecipient as $taskToNotifyRecipient) {
                    $message .= ' *'
                        . $taskToNotifyRecipient->title
                        . ' ('
                        . Carbon::createFromTimestamp($taskToNotifyRecipient->due_date)->format('Y-m-d')
                        . ')* '
                        . $webDomain
                        . 'projects/'
                        . $taskToNotifyRecipient->project_id
                        . '/sprints/'
                        . $taskToNotifyRecipient->sprint_id
                        . '/tasks/'
                        . $taskToNotifyRecipient->_id
                        . ' ';
                }

                /* Look if there are some tasks with due_date passed within project where recipient is PO*/
                $tasksToNotifyPo = [];
                foreach ($tasksDueDatePassed as $dueDateTasksArray) {
                    foreach ($dueDateTasksArray as $taskPassed) {
                        if ($recipient->id === $projects[$taskPassed->project_id]->acceptedBy) {
                            $tasksToNotifyPo[] = $taskPassed;
                        }
                    }
                }
                if (!empty($tasksToNotifyPo)) {
                    $sendDeadlinePassedMessage = true;
                }

                // Create message for tasks that due_date has passed for PO
                $messageDeadlinePassed = 'Hey, these tasks *due_date has passed*:';
                foreach ($tasksToNotifyPo as $taskDeadlinePassed) {
                    $messageDeadlinePassed .= ' *'
                        . $taskDeadlinePassed->title
                        . ' ('
                        . Carbon::createFromTimestamp($taskDeadlinePassed->due_date)->format('Y-m-d')
                        . ')* '
                        . $webDomain
                        . 'projects/'
                        . $taskDeadlinePassed->project_id
                        . '/sprints/'
                        . $taskDeadlinePassed->sprint_id
                        . '/tasks/'
                        . $taskDeadlinePassed->_id
                        . ' ';
                }
                // Send message for task due_dates
                if ($sendDueDatesMessage) {
                    Slack::sendMessage($recipientSlack, $message, Slack::LOW_PRIORITY);
                }
                // Send message to PO about tasks that deadline has passed
                if ($sendDeadlinePassedMessage) {
                    Slack::sendMessage($recipientSlack, $message, Slack::LOW_PRIORITY);
                }
            }
        }
    }
}
