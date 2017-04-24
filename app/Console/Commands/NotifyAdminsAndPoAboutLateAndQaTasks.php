<?php

namespace App\Console\Commands;

use App\GenericModel;
use App\Profile;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use App\Helpers\Slack;

class NotifyAdminsAndPoAboutLateAndQaTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ping:admins:late-and-qa-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cron: job that will send slack message to admin / PO with all late tasks and QA 
    waiting tasks';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $unixNow = (int)Carbon::now()->format('U');
        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
        $sendMessage = false;

        GenericModel::setCollection('tasks');
        // Get all late unfinished tasks (lower due_date then today)
        $lateTasks = GenericModel::where('due_date', '<=', $unixNow)
            ->where('ready', '=', true)
            ->where('passed_qa', '=', false)
            ->get();

        // Get all tasks that are submitted_for_qa
        $qaTasks = GenericModel::where('submitted_for_qa', '=', true)
            ->get();

        // Merge tasks
        $tasks = $lateTasks->merge($qaTasks);

        // Get all tasks projects so we can check project owners
        $projects = [];
        GenericModel::setCollection('projects');
        foreach ($tasks as $task) {
            if (!key_exists($task->project_id, $projects)) {
                $project = GenericModel::find($task->project_id);
                if ($project) {
                    $projects[$project->_id] = $project;
                }
            }
        }

        // Get all admins
        $admins = Profile::where('admin', '=', true)
            ->where('active', '=', true)
            ->get();

        // Get all project owners
        $projectOwners = [];
        foreach ($projects as $singleProject) {
            $po = Profile::where('_id', '=', $singleProject->acceptedBy)
                ->where('active', '=', true)
                ->first();
            if ($po) {
                $projectOwners[] = $po;
            }
        }

        // Merge admins and project owners
        $adminsAndPo = $admins->merge($projectOwners);

        // Loop through tasks, generate message and notify admins and project owners on slack about late tasks
        foreach ($adminsAndPo as $profileToNotify) {
            if ($profileToNotify->slack) {
                $recipient = '@' . $profileToNotify->slack;
                $message = 'Hey, these tasks are *late or waiting for QA*: ';
                foreach ($tasks as $taskToNotify) {
                    if ($profileToNotify->_id !== $projects[$taskToNotify->project_id]->acceptedBy
                        && !$profileToNotify->admin
                    ) {
                        continue;
                    }
                    $message .= ' *'
                        . $taskToNotify->title
                        . ' ('
                        . Carbon::createFromTimestamp($taskToNotify->due_date)->format('Y-m-d')
                        . ')* '
                        . ($taskToNotify->submitted_for_qa === true ? '*QA_READY*' : '*DUE_DATE_PASSED*')
                        . ' on project *'
                        . $projects[$taskToNotify->project_id]->name
                        . '* '
                        . $webDomain
                        . 'projects/'
                        . $taskToNotify->project_id
                        . '/sprints/'
                        . $taskToNotify->sprint_id
                        . '/tasks/'
                        . $taskToNotify->_id
                        . ' ';
                    if (!$sendMessage) {
                        $sendMessage = true;
                    }
                }
                // Send message
                if ($sendMessage) {
                    Slack::sendMessage($recipient, $message, Slack::HIGH_PRIORITY);
                    $sendMessage = false;
                }
            }
        }
    }
}
