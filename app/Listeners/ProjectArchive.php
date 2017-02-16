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

        $preSetCollection = GenericModel::getCollection();
        if ($preSetCollection === 'projects_archived') {
            //archive all project sprints
            GenericModel::setCollection('sprints');
            $projectSprints = GenericModel::where('project_id', '=', $project->id)->get();
            foreach ($projectSprints as $sprint) {
                $sprint['collection'] = 'sprints_archived';
                $sprint->save();
            }
            //archive all project tasks
            GenericModel::setCollection('tasks');
            $projectTasks = GenericModel::where('project_id', '=', $project->id)->get();
            foreach ($projectTasks as $task) {
                $task['collection'] = 'tasks_archived';
                $task->save();
            }
            GenericModel::setCollection($preSetCollection);
        }
    }
}
