<?php

namespace Tests\Controllers;

use Illuminate\Support\Facades\Hash;
use Tests\Collections\ProfileRelated;
use Tests\TestCase;
use App\Profile;

class GenericResourceControllerTest extends TestCase
{
    use ProfileRelated;

    public function setUp()
    {
        parent::setUp();

        $this->profile = Profile::create([
            'email' => 'ooo@test.com',
            'password' => Hash::make('testtest')
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->profile->delete();
    }

    public function testGenericResourceControllerIndexQuery()
    {
        $this->markTestIncomplete('need to finish test with JWT auth');

        $url = '/api/v1/app/starapi-testing/tasks';

        $resp = $this->get($url, $this->headers($this->profile));
    }
}
