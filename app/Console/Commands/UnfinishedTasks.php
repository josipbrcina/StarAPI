<?php

namespace App\Console\Commands;

use App\Helpers\Slack;
use Illuminate\Console\Command;
use App\GenericModel;
use Carbon\Carbon;
use App\Helpers\InputHandler;

/**
 * Class UnfinishedTasks
 * @package App\Console\Commands
 */
class UnfinishedTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unfinished:tasks:auto-move';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cron that moves unfinished tasks from sprint to following sprint on sprint end date.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Get all projects
        GenericModel::setCollection('projects');
        $projects = GenericModel::all();

        // Get all admin users
        GenericModel::setCollection('profiles');
        $admins = GenericModel::where('admin', '=', true)->get();

        $activeProjects = [];
        $sprints = [];

        // Get all active projects and project sprints
        GenericModel::setCollection('sprints');
        foreach ($projects as $project) {
            if (!empty($project->acceptedBy) && $project->isComplete !== true) {
                $activeProjects[$project->id] = $project;
                $projectSprints = GenericModel::where('project_id', '=', $project->id)->get();
                foreach ($projectSprints as $projectSprint) {
                    $sprints[$projectSprint->id] = $projectSprint;
                }
            }
        }

        $sprintEndedTasks = [];
        $endedSprints = [];
        $futureSprints = [];
        $futureSprintsStartDates = [];

        $unixNow = Carbon::now()->format('U');
        $checkDay = Carbon::now()->format('Y-m-d');

        // Get all unfinished tasks from ended sprints and get all future sprints on project
        GenericModel::setCollection('tasks');
        foreach ($sprints as $sprint) {
            $sprintStartDueDate =
                Carbon::createFromFormat('U', InputHandler::getUnixTimestamp($sprint->start))->format('Y-m-d');
            $sprintEndDueDate =
                Carbon::createFromFormat('U', InputHandler::getUnixTimestamp($sprint->end))->format('Y-m-d');
            if ($sprintEndDueDate < $checkDay) {
                $endedSprints[$sprint->project_id][] = $sprint;

                // Get all tasks and check if there are unfinished tasks
                $sprintTasks = GenericModel::where('sprint_id', '=', $sprint->id)->get();
                foreach ($sprintTasks as $task) {
                    if ($task->passed_qa !== true) {
                        $sprintEndedTasks[$task->id] = $task;
                    }
                }
                // Check start and end due dates for future sprints
            } elseif ($unixNow < $sprint->start || $checkDay === $sprintStartDueDate ||
                ($unixNow > $sprint->start && $checkDay <= $sprintEndDueDate)
            ) {
                $futureSprints[$sprint->project_id][] = $sprint;
                $futureSprintsStartDates[$sprint->project_id][] = $sprint->start;
            }
        }

        // Calculate on which projects are missing future sprints
        $missingSprints = array_diff_key($endedSprints, $futureSprints);
        $adminReport = [];

        foreach ($missingSprints as $project_id => $endedSprintsArray) {
            $adminReport[$project_id] = $activeProjects[$project_id]->name;
        }

        if (!empty($sprintEndedTasks)) {
            /* Ping on slack admins if there are no future sprints created so we can move unfinished tasks from sprint to
            following sprint on sprint end date*/
            foreach ($adminReport as $projectName) {
                foreach ($admins as $admin) {
                    if ($admin->slack && $admin->active) {
                        $recipient = '@' . $admin->slack;
                        $message = 'Hey! There are no future sprints created to move unfinished tasks from ended ' .
                            'sprints on project : *' . $projectName . '*';
                        Slack::sendMessage($recipient, $message, Slack::LOW_PRIORITY);
                    }
                }
            }

            // Move all unfinished tasks from ended sprint to following one
            foreach ($futureSprints as $projectId => $futureSprintsArray) {
                foreach ($futureSprintsArray as $futureSprint) {
                    if ($futureSprint->start === min($futureSprintsStartDates[$futureSprint->project_id])) {
                        foreach ($sprintEndedTasks as $task) {
                            if ($task->project_id === $futureSprint->project_id) {
                                $task->sprint_id = $futureSprint->_id;
                                $task->save();
                                $this->info('Task ' . $task->title . ' moved to sprint ' . $futureSprint->title);
                            }
                        }
                    }
                }
            }
        }
    }
}
