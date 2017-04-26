<?php

namespace Tests\Controllers;

use App\Http\Controllers\AccountController;
use App\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\ParameterBag;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AccountControllerTest extends TestCase
{
    /**
     * Test invalid login attempt
     */
    public function testEmptyRequestOnLogin()
    {
        $this->markTestSkipped();
        $request = new Request();
        $controller = new AccountController($request);
        $response = $controller->login($request);

        $this->assertEquals(
            [
                'error' => true,
                'errors' => [
                    'Invalid credentials.',
                ],
            ],
            $response->getData(true)
        );
    }

    /**
     * Test valid login attempt
     */
    public function testValidLogin()
    {
        $this->markTestSkipped();
        $authParams = [
            'email' => 'sample@email.com',
            'password' => 'samplePass',
        ];

        $request = new Request();
        $request->setMethod('POST');

        $request->request = new ParameterBag($authParams);

        JWTAuth::shouldReceive('attempt')
            ->once()
            ->with($authParams)
            ->andReturn('token-sample');

        JWTAuth::shouldReceive('setToken');
        JWTAuth::shouldReceive('getToken');

        $profile = new Profile($authParams);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($profile);

        $controller = new AccountController($request);
        $response = $controller->login($request);

        $this->assertEquals(
            [
                'email' => $authParams['email']
            ],
            $response->getData(true)
        );
    }
}
