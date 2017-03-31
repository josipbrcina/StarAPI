<?php

namespace App\Console\Commands;

use App\GenericModel;
use App\Helpers\Slack;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use App\Profile;

/**
 * Class NotifyAdminsQaWaitingTasks
 * @package App\Console\Commands
 */
class NotifyAdminsQaWaitingTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ping:admins:qa:waiting:tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cron job that will ping admins on slack about tasks that were submitted for QA yesterday';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dateYesterday = Carbon::yesterday()->format('Y-m-d');
        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');

        // Get all tasks that are submitted for QA
        GenericModel::setCollection('tasks');
        $tasksInQa = GenericModel::where('submitted_for_qa', '=', true)
            ->get();

        // Get projects and project owners
        $projects = [];
        $projectOwners = [];
        foreach ($tasksInQa as $task) {
            if (!array_key_exists($task->project_id, $projects)) {
                GenericModel::setCollection('projects');
                $project = GenericModel::find($task->project_id);
                if ($project) {
                    $projects[$project->_id] = $project;
                }
            }
        }
        foreach ($projects as $project) {
            $profile = Profile::find($project->acceptedBy);
            if ($profile) {
                $projectOwners[] = $profile;
            }
        }

        /*Loop through project owners and tasks, check if there are tasks that are submitted for QA yesterday and
        create message and send to project owners*/
        foreach ($projectOwners as $projectOwner) {
            if ($projectOwner->slack) {
                $recipient = '@' . $projectOwner->slack;
                $message = 'Hey, these tasks are *submitted for QA yesterday* and waiting for review:';

                foreach ($tasksInQa as $task) {
                    if ($projectOwner->_id === $projects[$task->project_id]->acceptedBy) {
                        foreach ($task->task_history as $historyRecord) {
                            if ($historyRecord['status'] === 'qa_ready'
                                && Carbon::createFromTimestamp($historyRecord['timestamp'])->format('Y-m-d')
                                === $dateYesterday
                            ) {
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
                        }
                    }
                }
                // Send message
                Slack::sendMessage($recipient, $message, Slack::HIGH_PRIORITY);
            }
        }
    }
}
