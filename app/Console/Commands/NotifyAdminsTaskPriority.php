<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\GenericModel;
use Carbon\Carbon;
use App\Profile;
use App\Helpers\InputHandler;
use App\Helpers\Slack;

class NotifyAdminsTaskPriority extends Command
{
    const HIGH = 'High';
    const MEDIUM = 'Medium';
    const LOW = 'Low';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ping:admins:task:priority';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify admins and Pos on slack about task priority deadlines.';

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
        $unixTime2Days = Carbon::now()->addDays(2)->format('U');
        $unixTime7Days = Carbon::now()->addDays(7)->format('U');
        $unixTime14Days = Carbon::now()->addDays(14)->format('U');
        $unixTime28Days = Carbon::now()->addDays(28)->format('U');

        $tasksDueDateNextTwoDays = [];
        $tasksDueDates = [];

        foreach ($tasks as $task) {
            if (empty($task->owner)) {
                $taskDueDate = InputHandler::getUnixTimestamp($task->due_date);
                //get task if task priority is High and due_date is in next 2 days
                if ($taskDueDate >= $unixTimeNow && $taskDueDate <= $unixTime2Days && $task->priority === self::HIGH) {
                    $tasksDueDateNextTwoDays[$task->project_id][] = $task;
                }

                //check if task priority is High and due_date is between next 2-7 days, add counter
                if ($taskDueDate > $unixTime2Days && $taskDueDate <= $unixTime7Days && $task->priority === self::HIGH) {
                    if (!key_exists($task->project_id, $tasksDueDates)) {
                        $tasksDueDates[$task->project_id] = $this->getTaskDueDateArrayStructure(self::HIGH);
                    } else {
                        $tasksDueDates[$task->project_id]['High']++;
                    }
                }
                //check if task priority is Medium and due_date is between next 8-14 days, add counter
                if ($taskDueDate > $unixTime7Days && $taskDueDate <= $unixTime14Days && $task->priority
                    === self::MEDIUM
                ) {
                    if (!key_exists($task->project_id, $tasksDueDates)) {
                        $tasksDueDates[$task->project_id] = $this->getTaskDueDateArrayStructure(self::MEDIUM);
                    } else {
                        $tasksDueDates[$task->project_id]['Medium']++;
                    }
                }
                //check if task priority is Low and due_date is between next 15-28 days, add counter
                if ($taskDueDate > $unixTime14Days && $taskDueDate <= $unixTime28Days && $task->priority
                    === self::LOW
                ) {
                    if (!key_exists($task->project_id, $tasksDueDates)) {
                        $tasksDueDates[$task->project_id] = $this->getTaskDueDateArrayStructure(self::LOW);
                    } else {
                        $tasksDueDates[$task->project_id]['Low']++;
                    }
                }
            }
        }

        $projectOwnerIds = [];
        $projects = [];

        //Get all tasks projects and project owner IDs
        GenericModel::setCollection('projects');
        $readProjectIdsFrom = array_merge($tasksDueDateNextTwoDays, $tasksDueDates);
        foreach ($readProjectIdsFrom as $projectId => $taskStats) {
            $project = GenericModel::where('_id', '=', $projectId)->first();
            $projects[$projectId] = $project;
            if ($project->acceptedBy) {
                $projectOwnerIds[] = $project->acceptedBy;
            }
        }

        $recipients = Profile::all();

        //send slack notification to all admins and POs about task priority deadlines
        foreach ($recipients as $recipient) {
            if ($recipient->admin === true || in_array($recipient->id, $projectOwnerIds) && $recipient->slack) {
                foreach ($projects as $projectToNotify) {
                    if ($recipient->admin !== true && $recipient->id !== $projectToNotify->acceptedBy) {
                        continue;
                    }
                    $sendTo = '@' . $recipient->slack;
                    $webDomain = \Config::get('sharedSettings.internalConfiguration.webDomain');
                    //send notification per project about tasks deadline in next 2 days for High priority tasks
                    foreach ($tasksDueDateNextTwoDays as $taskProjectId => $taskList) {
                        if ($taskProjectId === $projectToNotify->id) {
                            foreach ($taskList as $singleTask) {
                                $message = 'Task *'
                                    . $singleTask->title
                                    . '* due date in next *2 days* '
                                    . $webDomain
                                    . 'projects/'
                                    . $singleTask->project_id
                                    . '/sprints/'
                                    . $singleTask->sprint_id
                                    . '/tasks/'
                                    . $singleTask->_id;
                                Slack::sendMessage($sendTo, $message, Slack::LOW_PRIORITY);
                            }
                        }
                    }

                    /*Send notification per project about task deadlines for High priority in next 7 days,
                    Medium priority in next 14 days, and low priority in next 28 days*/
                    if (key_exists($projectToNotify->id, $tasksDueDates)) {
                        foreach ($tasksDueDates[$projectToNotify->id] as $priority => $tasksCounted) {
                            $message =
                                'On project *'
                                . $projectToNotify->name
                                . '*, there are *'
                                . $tasksCounted;
                            if ($priority === self::HIGH) {
                                $message .= '* tasks with *High priority* in next *7 days*';
                            }
                            if ($priority === self::MEDIUM) {
                                $message .= '* tasks with *Medium priority* in next *14 days*';
                            }
                            if ($priority === self::LOW) {
                                $message .= '* tasks with *Low priority* in next *28 days*';
                            }
                            Slack::sendMessage($sendTo, $message, Slack::LOW_PRIORITY);
                        }
                    }
                }
            }
        }

        GenericModel::setCollection($preSetCollection);
    }

    /**
     * Helper to get array with proper structure for task due dates counting
     * @param $priority
     * @return array
     */
    private function getTaskDueDateArrayStructure($priority)
    {
        $taskDueDates = [
            'High' => 0,
            'Medium' => 0,
            'Low' => 0
        ];

        if ($priority === self::HIGH) {
            $taskDueDates['High']++;
        }
        if ($priority === self::MEDIUM) {
            $taskDueDates['High']++;
        }
        if ($priority === self::LOW) {
            $taskDueDates['High']++;
        }

        return $taskDueDates;
    }
}
