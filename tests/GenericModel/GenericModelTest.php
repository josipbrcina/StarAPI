<?php

namespace Tests\GenericModel;

use Tests\Collections\ProjectRelated;
use Tests\TestCase;
use App\GenericModel;

class GenericModelTest extends TestCase
{
    use ProjectRelated;

    /**
     * Test model archive
     */
    public function testGenericModelArchive()
    {
        $project = $this->getNewProject();
        $project->save();

        $projectId = $project->id;
        $project->archive();

        GenericModel::setCollection('projects');
        $oldProject = GenericModel::find($projectId);

        GenericModel::setCollection('projects_archived');
        $foundArchivedProject = GenericModel::find($projectId);

        $this->assertEquals($projectId, $foundArchivedProject->id);
        $this->assertEquals(null, $oldProject);
    }

    /**
     * Test model unArchive with wrong collection - exception error
     */
    public function testGenericModelArchiveWrongCollection()
    {
        $project = $this->getNewArchivedProject();
        $project->save();

        $this->setExpectedException('Exception', 'Model collection now allowed to archive', 403);
        $project->archive();
    }

    /**
     * Test model unArchive
     */
    public function testGenericModelunArchive()
    {
        $project = $this->getNewArchivedProject();
        $project->save();

        $archivedProjectId = $project->id;

        $project->unArchive();

        GenericModel::setCollection('projects_archived');
        $archivedProject = GenericModel::find($archivedProjectId);

        GenericModel::setCollection('projects');
        $foundProject = GenericModel::find($archivedProjectId);

        $this->assertEquals($archivedProjectId, $foundProject->id);
        $this->assertEquals(null, $archivedProject);
    }

    /**
     * Test model unArchive with wrong collection - exception error
     */
    public function testGenericModelunArchiveWrongCollection()
    {
        $project = $this->getNewProject();
        $project->save();

        $this->setExpectedException('Exception', 'Model collection now allowed to unArchive', 403);
        $project->unarchive();
    }

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
