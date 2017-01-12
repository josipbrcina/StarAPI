<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\GenericModel;

/**
 * Class SprintReminder
 * @package App\Console\Commands
 */
class SprintReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sprint:remind';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check sprint tasks due dates and ping task owner 1 day before task end_due_date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        GenericModel::setCollection('projects');
        $projects = GenericModel::all();

        $activeProjects = [];
        $members = [];
        $sprints = [];
        $tasks = [];

        $date = new \DateTime();
        $unixDate = $date->format('U');

        // Get all active projects, members of projects and sprints
        foreach ($projects as $project) {
            if (!empty($project->acceptedBy) && $project->isComplete !== true && !empty($project->sprints)) {
                $activeProjects[$project->id] = $project;
                GenericModel::setCollection('sprints');
                foreach ($project->sprints as $sprintId) {
                    $sprint = GenericModel::where('_id', '=', $sprintId)->first();
                    if ($unixDate >= $sprint->start && $unixDate <= $sprint->end) {
                        $sprints[$sprintId] = $sprint;
                    }
                }
                GenericModel::setCollection('profiles');
                foreach ($project->members as $memberId) {
                    $member = GenericModel::where('_id', '=', $memberId)->first();
                    $members[$memberId] = $member;
                }
            }
        }

        // Get all active tasks
        GenericModel::setCollection('tasks');
        foreach ($sprints as $sprint) {
            if (!empty($sprint->tasks)) {
                foreach ($sprint->tasks as $taskId) {
                    $task = GenericModel::where('_id', '=', $taskId)->first();
                    if (empty($task->owner)) {
                        $tasks[$taskId] = $task;
                    }
                }
            }
        }

        // Ping on slack all users on active projects about unassigned tasks on active sprints
        $taskCount = [];
        foreach ($tasks as $task) {
            if (!key_exists($task->project_id, $taskCount)) {
                $taskCount[$task->project_id] = 1;
            } else {
                $taskCount[$task->project_id]++;
            }
        }

        foreach ($activeProjects as $project) {
            foreach ($members as $member) {
                if (in_array($member->_id, $project->members) && $member->slack) {
                    $recipient = '@' . $member->slack;
                    $projectName = $project->name;
                    $unassignedTasks = $taskCount[$project->_id];
                    $message = '*Reminder*:'
                        . 'There are * '
                        . $unassignedTasks
                        . '* unassigned tasks on active sprints'
                        . ', for project *'
                        . $projectName
                        . '*)';
                    \SlackChat::message($recipient, $message);
                }
            }
        }
    }
}
