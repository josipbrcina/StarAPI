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
            GenericModel::setCollection('sprints')
            : GenericModel::setCollection('sprints_archived');

        $projectSprints = GenericModel::where('project_id', '=', $project->id)->get();

        //check if project is archived or unArchived to set proper collection for sprints
        $project['collection'] === 'projects_archived' ?
            GenericModel::setCollection('sprints_archived')
            : GenericModel::setCollection('sprints');

        foreach ($projectSprints as $sprint) {
            $archivedSprint = $sprint->replicate();
            $archivedSprint->_id = $sprint->_id;
            $project['collection'] === 'projects_archived' ?
                $archivedSprint['collection'] = 'sprints_archived'
            : $archivedSprint['collection'] = 'sprints';
            if ($archivedSprint->save()) {
                $sprint->delete();
            }
        }

        //check if project is archived or unArchived to get all tasks from proper collection
        $project['collection'] === 'projects_archived' ?
            GenericModel::setCollection('tasks')
            : GenericModel::setCollection('tasks_archived');

        $projectTasks = GenericModel::where('project_id', '=', $project->id)->get();

        //check if project is archived or unArchived to set proper collection for tasks
        $project['collection'] === 'projects_archived' ?
            GenericModel::setCollection('tasks_archived')
            : GenericModel::setCollection('tasks');

        foreach ($projectTasks as $task) {
            $archivedTask = $task->replicate();
            $archivedTask->_id = $task->_id;
            $project['collection'] === 'projects_archived' ?
                $archivedSprint['collection'] = 'tasks_archived'
                : $archivedSprint['collection'] = 'tasks';
            if ($archivedTask->save()) {
                $task->delete();
            }
        }
    }
}
