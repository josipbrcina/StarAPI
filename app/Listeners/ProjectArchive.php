<?php

namespace App\Listeners;

use App\GenericModel;

class ProjectArchive
{
    /**
     * Handle the event.
     * @param \App\Events\ProjectArchive $event
     */
    public function handle(\App\Events\ProjectArchive $event)
    {
        $project = $event->model;

        //check if project is archived or unArchived to get all sprints from proper collection
        $project['collection'] === 'projects_archived' ?
            $projectSprints = GenericModel::whereTo('sprints')
                ->where('project_id', '=', $project->id)
                ->get()
            : $projectSprints = GenericModel::whereTo('sprints_archived')
            ->where('project_id', '=', $project->id)
            ->get();

        //archive or unArchive project sprints
        foreach ($projectSprints as $sprint) {
            $project['collection'] === 'projects_archived' ? $sprint->archive() : $sprint->unArchive();
        }

        //check if project is archived or unArchived to get all tasks from proper collection
        $project['collection'] === 'projects_archived' ?
            $projectTasks = GenericModel::whereTo('tasks')
                ->where('project_id', '=', $project->id)
                ->get()
            : $projectTasks = GenericModel::whereTo('tasks_archived')
            ->where('project_id', '=', $project->id)
            ->get();

        //archive or unArchive project tasks
        foreach ($projectTasks as $task) {
            $project['collection'] === 'projects_archived' ? $task->archive() : $task->unArchive();
        }
    }
}
