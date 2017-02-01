<?php

namespace Tests;

use App\Profile;
use App\Validation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use ValidationsSeeder;

class ValidationControllerTest extends TestCase
{
    use DatabaseMigrations;

    private $url = 'api/v1/app/starapi-testing/';
    private $profile = null;

    public function setUp()
    {
        parent::setUp();

        $this->seed(ValidationsSeeder::class);
        $this->profile = new Profile([
            'name' => 'test',
            'email' => 'test@test.com'
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * test ValidationController index
     *
     * @return void
     */
    public function testIndex()
    {
        $response = $this->call('GET', $this->url . 'validations', [], $this->headers($this->profile));

        $this->assertResponseOk();
        $this->assertJson($response->getContent());
    }

    /**
     * test ValidationController store
     * @return mixed
     */
    public function testStore()
    {
        $this->loginWithFakeUser();
        $response = $this->call('POST', $this->url . 'validations', [
            'fields' => [
                'title' => 'alpha_num',
                'test' => 'required|string',
            ],
            'resource' => 'testing',
            'acl' => [
                'standard' => [
                    'editable' => [],
                    'GET' => true,
                    'DELETE' => false,
                    'POST' => false,
                    'updateOwn' => false
                ]
            ]
        ]);

        $this->assertResponseOk();
        $this->assertJson($response->getContent());
    }

    /**
     * test ValidationController show
     */
    public function testShow()
    {
        $this->loginWithFakeUser();
        $validation = Validation::where('resource', '=', 'tasks')->first();
        $response = $this->call('GET', $this->url . 'validations/' . $validation->_id);

        $this->assertResponseOk();
        $this->assertJson($response->getContent());
    }

    /**
     * test ValidationController show with wrong ID
     */
    public function testShowFailedId()
    {
        $this->loginWithFakeUser();
        $response = $this->call('GET', $this->url . 'validations/111222333444');

        $this->assertResponseStatus(404);
        $this->assertJson($response->getContent());
    }

    /**
     * test ValidationController update
     */
    public function testUpdate()
    {
        $validation = Validation::where('resource', '=', 'uploads')->first();
        $response = $this->call('PUT', $this->url . 'validations/' . $validation->_id, [
            'fields' => 'testing update',
            'resource' => 'testing update'
        ]);

        $this->assertResponseOk();
        $this->assertJson($response->getContent());
    }

    /**
     * test ValidationController update with wrong ID
     */
    public function testUpdateFailedId()
    {
        $response = $this->call('PUT', $this->url . 'validations/1111122255555', [
            'fields' => 'testing update',
            'resource' => 'testing update'
        ]);

        $this->assertResponseStatus(404);
        $this->assertJson($response->getContent());
    }

    /**
     * test ValidationController delete
     */
    public function testDelete()
    {
        $validation = Validation::where('resource', '=', 'projects')->first();
        $response = $this->call('DELETE', $this->url . 'validations/' . $validation->_id);

        $this->assertResponseOk();
        $this->assertJson($response->getContent());
    }

    /**
     * test ValidationController delete with wrong ID
     */
    public function testDeleteFailedId()
    {
        $response = $this->call('DELETE', $this->url . 'validations/43435353535');

        $this->assertResponseStatus(404);
        $this->assertJson($response->getContent());
    }
}
