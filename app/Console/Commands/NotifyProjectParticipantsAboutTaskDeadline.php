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
        GenericModel::setCollection('tasks');

        $unixNow = (int)Carbon::now()->format('U');
        // Unix timestamp 1 day before now at the beginning of the fay
        $unixYesterday = (int)Carbon::now()->subDay(1)->startOfDay()->format('U');
        // Unix timestamp 7 days from now at the end of the day
        $unixSevenDaysFromNow = (int)Carbon::now()->addDays(7)->endOfDay()->format('U');

        // Get all unfinished tasks with due_date between yesterday and next 7 days
        $tasks = GenericModel::where('due_date', '<=', $unixSevenDaysFromNow)
            ->where('due_date', '>=', $unixYesterday)
            ->where('ready', '=', true)
            ->where('passed_qa', '=', false)
            ->get();

        GenericModel::setCollection('projects');
        $getProjects = GenericModel::all();

        $allProjects = [];
        foreach ($getProjects as $project) {
            $allProjects[$project->id] = $project;
        }

        $tasksDeadlinePassed = [];
        $tasksDueDateIn7Days = [];
        $profiles = Profile::where('active', '=', true)
            ->get();

        foreach ($tasks as $task) {
            if ($task->due_date <= $unixNow) {
                $tasksDeadlinePassed[] = $task;
            } else {
                $tasksDueDateIn7Days[$task->due_date] = $task;
            }
        }

        ksort($tasksDueDateIn7Days);
        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
        foreach ($profiles as $recipient) {
            if ($recipient->slack) {
                $counter = 1;
                $message = 'Hey, tasks due_date soon:';
                foreach ($tasksDueDateIn7Days as $taskToNotify) {
                    if (!$recipient->admin
                        && $recipient->id !== $allProjects[$taskToNotify->project_id]->acceptedBy
                        && !in_array($recipient->id, $allProjects[$taskToNotify->project_id]->members)
                    ) {
                        continue;
                    }
                    $compareSkills = array_intersect($recipient->skills, $taskToNotify->skillset);
                    if (!empty($compareSkills)) {
                        $message .= ' *'
                            . $taskToNotify->title
                            . ' ('
                            . Carbon::createFromTimestamp($taskToNotify->due_date)->format('Y-m-d')
                            . ')* '
                            . $webDomain
                            . 'projects/'
                            . $taskToNotify->project_id
                            . '/sprints/'
                            . $taskToNotify->sprint_id
                            . '/tasks/'
                            . $taskToNotify->_id
                            . ($counter < 3 ? ', ' : '');
                        if ($counter === 3) {
                            $recipientSlack = '@' . $recipient->slack;
                            SlackChat::message($recipientSlack, $message);
                            //Slack::sendMessage($recipientSlack, $message, Slack::LOW_PRIORITY);
                            break;
                        }
                        $counter++;
                    }
                }
            }
        }
    }
}
