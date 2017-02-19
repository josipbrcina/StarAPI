<?php

namespace Tests\GenericModel;

use Tests\Collections\ProjectRelated;
use Tests\TestCase;
use App\GenericModel;

class GenericModelTest extends TestCase
{
    use ProjectRelated;

    /**
     * Test GenericModel delete
     */
    public function testGenericModelDelete()
    {
        $project = $this->getNewProject();
        $project->save();

        $projectId = $project->id;
        $project->delete();

        GenericModel::setCollection('projects');
        $oldProject = GenericModel::find($projectId);

        GenericModel::setCollection('projects_deleted');
        $foundDeletedProject = GenericModel::find($projectId);

        $this->assertEquals($projectId, $foundDeletedProject->id);
        $this->assertEquals(null, $oldProject);
    }
}
