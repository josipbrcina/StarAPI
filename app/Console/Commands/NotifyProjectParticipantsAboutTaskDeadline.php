<?php

namespace App\Console\Commands;

use App\GenericModel;
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
    const DUE_DATE_PASSED = 'due_date_passed';
    const DUE_DATE_SOON = 'due_date_soon';
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
        'Ping admins,project members and project owner on slack about task deadlines within next 7 days, ping project
        owner about tasks that deadline has passed.';

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
        $tasks = GenericModel::whereTo('tasks')
            ->where('due_date', '<=', $unixSevenDaysFromNow)
            ->where('due_date', '>=', $unixYesterday)
            ->where('ready', '=', true)
            ->where('passed_qa', '=', false)
            ->get();

        $projects = [];
        $tasksDueDatePassed = [];
        $tasksDueDateIn7Days = [];

        foreach ($tasks as $task) {
            if (!array_key_exists($task->project_id, $projects)) {
                $project = GenericModel::whereTo('projects')->find($task->project_id);
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

        $profiles = Profile::where('active', '=', true)
            ->get();
        foreach ($profiles as $recipient) {
            if ($recipient->slack) {
                $recipientSlack = '@' . $recipient->slack;

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

                /* Look if there are some tasks with due_date passed within project where recipient is PO*/
                $tasksToNotifyPo = [];
                foreach ($tasksDueDatePassed as $dueDateTasksArray) {
                    foreach ($dueDateTasksArray as $taskPassed) {
                        if ($recipient->id === $projects[$taskPassed->project_id]->acceptedBy) {
                            $tasksToNotifyPo[] = $taskPassed;
                        }
                    }
                }

                // Create message for tasks with due_date within next 7 days
                $messageDeadlineSoon = $this->createMessage(self::DUE_DATE_SOON, $tasksToNotifyRecipient);
                if ($messageDeadlineSoon) {
                    Slack::sendMessage(
                        $recipientSlack,
                        $messageDeadlineSoon,
                        Slack::LOW_PRIORITY
                    );
                }
                // Create message for tasks that due_date has passed for PO
                $messageDeadlinePassed = $this->createMessage(self::DUE_DATE_PASSED, $tasksToNotifyPo);
                if ($messageDeadlinePassed) {
                    Slack::sendMessage(
                        $recipientSlack,
                        $messageDeadlinePassed,
                        Slack::LOW_PRIORITY
                    );
                }
            }
        }
    }

    /**
     * Helper for creating message about tasks deadline
     * @param array $tasks
     * @param $format
     * @return bool|string
     */
    private function createMessage($format, array $tasks = [])
    {
        if (empty($tasks)) {
            return false;
        }

        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
        $message = '';

        if ($format === self::DUE_DATE_SOON) {
            $message = 'Hey, these tasks *due_date soon*:';
        }
        if ($format === self::DUE_DATE_PASSED) {
            $message = 'Hey, these tasks *due_date has passed*:';
        }

        foreach ($tasks as $task) {
            $message .= ' *'
                . $task->title
                . ' ('
                . Carbon::createFromTimestamp($task->due_date)->format('Y-m-d')
                . ')* '
                . $webDomain
                . 'projects/'
                . $task->project_id
                . '/sprints/'
                . $task->sprint_id
                . '/tasks/'
                . $task->_id
                . ' ';
        }

        return $message;
    }
}
