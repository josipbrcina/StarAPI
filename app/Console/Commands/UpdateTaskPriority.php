<?php

namespace App\Console\Commands;

use App\GenericModel;
use App\Helpers\InputHandler;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Profile;
use App\Helpers\Slack;

class UpdateTaskPriority extends Command
{
    const HIGH = 'High';
    const MEDIUM = 'Medium';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:task:priority';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update task priorities based on deadline.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $preSetCollection = GenericModel::getCollection();
        GenericModel::setCollection('tasks');

        $tasks = GenericModel::all();
        $unixTimeNow = Carbon::now()->format('U');
        $unixTime7Days = Carbon::now()->addDays(7)->format('U');
        $unixTime14Days = Carbon::now()->addDays(14)->format('U');

        $tasksBumpedPerProject = [];

        foreach ($tasks as $task) {
            if (empty($task->owner)) {
                $taskDueDate = InputHandler::getUnixTimestamp($task->due_date);
                //check if task due_date is in next 7 days and switch task priority to High if not set already
                if ($taskDueDate >= $unixTimeNow && $taskDueDate <= $unixTime7Days && $task->priority !== self::HIGH) {
                    $task->priority = self::HIGH;
                    $task->save();
                    if (!key_exists($task->project_id, $tasksBumpedPerProject)) {
                        $tasksBumpedPerProject[$task->project_id]['High'] = 1;
                        $tasksBumpedPerProject[$task->project_id]['Medium'] = 0;
                    } else {
                        $tasksBumpedPerProject[$task->project_id]['High']++;
                    }
                }
                /*check if task due_date is between next 8 - 14 days and switch task priority to Medium if not set
                 already*/
                if ($taskDueDate > $unixTime7Days && $taskDueDate <= $unixTime14Days && $task->priority
                    !== self::MEDIUM
                ) {
                    $task->priority = self::MEDIUM;
                    $task->save();
                    if (!key_exists($task->project_id, $tasksBumpedPerProject)) {
                        $tasksBumpedPerProject[$task->project_id]['High'] = 0;
                        $tasksBumpedPerProject[$task->project_id]['Medium'] = 1;
                    } else {
                        $tasksBumpedPerProject[$task->project_id]['Medium']++;
                    }
                }
            }
        }

        $projectOwnerIds = [];
        $projects = [];

        //Get all tasks projects and project owner IDs
        GenericModel::setCollection('projects');
        foreach ($tasksBumpedPerProject as $projectId => $count) {
            $project = GenericModel::where('_id', '=', $projectId)->first();
            $projects[$projectId] = $project;
            if ($project->acceptedBy) {
                $projectOwnerIds[] = $project->acceptedBy;
            }
        }

        $recipients = Profile::all();

        //send slack notification to all admins and POs about task priority change
        foreach ($recipients as $recipient) {
            if ($recipient->admin === true || in_array($recipient->id, $projectOwnerIds) && $recipient->slack) {
                foreach ($projects as $projectToNotify) {
                    if ($recipient->admin !== true && $recipient->id !== $projectToNotify->acceptedBy) {
                        continue;
                    }
                    $sendTo = '@' . $recipient->slack;
                    $message =
                        'On project *'
                        . $projectToNotify->name
                        . '*, there are *'
                        . $tasksBumpedPerProject[$projectToNotify->id]['High']
                        . '* tasks bumped to *High priority* '
                        . 'and *'
                        . $tasksBumpedPerProject[$projectToNotify->id]['Medium']
                        . '* bumped to *Medium priority*';
                    Slack::sendMessage($sendTo, $message, Slack::LOW_PRIORITY);
                }
            }
        }

        GenericModel::setCollection($preSetCollection);
    }
}
