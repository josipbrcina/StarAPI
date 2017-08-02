<?php

namespace Tests\Controllers;

use App\GenericModel;
use App\Http\Controllers\GenericResourceController;
use Tests\Collections\ProfileRelated;
use Tests\Collections\ProjectRelated;
use Tests\TestCase;
use App\Profile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

class GenericResourceControllerTest extends TestCase
{
    use ProfileRelated, ProjectRelated;

    public function setUp()
    {
        parent::setUp();

        $this->profile = Profile::create();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->profile->delete();
    }

    /**
     * Test generic resource controller index query for value range
     */
    public function testGenericResourceControllerIndexQueryRange()
    {
        // Clear tasks collection
        GenericModel::setCollection('tasks');
        GenericModel::truncate();

        // Create some tasks and set priority
        for ($i = 1; $i < 11; $i++) {
            $task = $this->getAssignedTask();
            $task->priority = $i;
            $task->save();
        }

        // Query parameters
        $queryParams = [
            'priority' => '1 >=<5',
        ];

        // Set request and call controller
        $request = new Request();
        $request->setMethod('GET');

        $request->query = new ParameterBag($queryParams);

        $controller = new GenericResourceController($request);
        $response = $controller->index($request);
        $this->assertCount(5, $response);

        // Query parameters
        $queryParams = [
            'priority' => '7 >=< 10',
        ];

        // Set request and call controller
        $request = new Request();
        $request->setMethod('GET');

        $request->query = new ParameterBag($queryParams);

        $controller = new GenericResourceController($request);
        $response = $controller->index($request);
        $this->assertCount(4, $response);
    }

    /**
     * Test generic resource controller index query for value range with errors
     */
    public function testGenericResourceControllerIndexQueryRangeWithErrors()
    {
        $this->markTestSkipped('development mode.');
        // Query parameters
        $queryParams = [
            'name' => 'a >=< b >=< c'
        ];

        // Set request and call controller
        $request = new Request();
        $request->setMethod('GET');

        $request->query = new ParameterBag($queryParams);

        $controller = new GenericResourceController($request);
        $response = $controller->index($request);
        ;
        $errorMsg = '{"error":true,"errors":["Range search must be between two values."]}';
        $this->assertObjectHasAttribute('content', $response);
        $this->assertAttributeContains($errorMsg, 'content', $response);
    }

    /**
     * Test generic resource controller index query for value range with looseSearch
     */
    public function testGenericResourceControllerIndexQueryRangeWithLooseSearch()
    {
        // Clear tasks collection
        GenericModel::setCollection('tasks');
        GenericModel::truncate();

        $title = ['test', 'a', 'b', 'ac', 'd', 'e', 'fa', 'testing', 'aaBbA'];

        // Create some tasks, set priority and title
        for ($i = 1; $i < 7; $i++) {
            $task = $this->getAssignedTask();
            $task->priority = $i;
            $task->title = $title[$i];
            $task->save();
        }

        // Query parameters
        $queryParams = [
            'looseSearch' => true,
            'title' => 'A',
            'priority' => '1 >=< 5',
        ];

        // Set request and call controller
        $request = new Request();
        $request->setMethod('GET');

        $request->query = new ParameterBag($queryParams);

        $controller = new GenericResourceController($request);
        $response = $controller->index($request);
        $this->assertCount(2, $response);
    }

    public function testGenericResourceControllerIndexQueryRangeDoubleAndWithStringRange()
    {
        // Clear tasks collection
        GenericModel::setCollection('tasks');
        GenericModel::truncate();

        $title = ['test', 'A', 'b', 'aaaa', 'dddddd', 'e', 'fa', 'testing',];

        // Create some tasks, set priority and title
        for ($i = 1; $i < 7; $i++) {
            $task = $this->getAssignedTask();
            $task->priority = $i;
            $task->title = $title[$i];
            $task->save();
        }

        // Query parameters
        $queryParams = [
            'title' => 'A >=< b',
            'priority' => '1 >=< 4'
        ];

        // Set request and call controller
        $request = new Request();
        $request->setMethod('GET');

        $request->query = new ParameterBag($queryParams);

        $controller = new GenericResourceController($request);
        $response = $controller->index($request);
        $this->assertCount(3, $response);
    }
}
