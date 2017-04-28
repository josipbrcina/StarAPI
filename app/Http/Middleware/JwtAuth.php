<?php

namespace App\Http\Middleware;

use App\Account;
use App\GenericModel;
use App\Helpers\AuthHelper;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Tymon\JWTAuth\Middleware\BaseMiddleware;

class JwtAuth extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        if (!$token = $this->auth->setRequest($request)->getToken()) {
            return $this->response->json(['errors' => ["Not logged in."]], 401);
        }

        // Set database connection to "accounts"
        AuthHelper::setDatabaseConnection();

        $user = $this->auth->authenticate($token);

        $this->events->fire('tymon.jwt.valid', $user);

        // Set authenticated user to app instance so we can call it from AuthHelper
        $app = App::getFacadeRoot();
        $app->instance('authenticatedUser', $user);

        $userCheck = AuthHelper::getAuthenticatedUser();

        if (!$userCheck instanceof Account) {
            return $this->response->json(['errors' => ["Not logged in."]], 401);
        }

        $appName = $request->route('appName');
        $formattedAppName = strtolower($appName);

        $coreDatabaseName = Config::get('sharedSettings.internalConfiguration.coreDatabaseName');
        // If user is admin and request route is core database, set connection and allow admins to write into database
        if ($userCheck->admin === true && $coreDatabaseName === $formattedAppName) {
            AuthHelper::setDatabaseConnection($coreDatabaseName);
            return $next($request);
        }

        // Set database connection to request "appName"
        AuthHelper::setDatabaseConnection($appName);

        /* Check account applications to see if he is registered to requested app and check application profiles to
        see if there is profile related to that account*/
        $method = $request->method();
        $uri = $request->getRequestUri();
        $joinApp = '/api/v1/app/' . $appName . '/application/join';
        $leaveApp = '/api/v1/app/' . $appName . '/application/leave';
        $createApp = '/api/v1/app/' . $appName . '/application/create';

        // Allow only "join/leave application" route otherwise do validation to see if user exists on application
        if ($formattedAppName !== 'accounts'
            && $method === 'POST'
            && $uri === $joinApp
            || $uri === $leaveApp
        ) {
            return $next($request);
        }

        // If route is to create application check if it's correct database - accounts
        if ($formattedAppName !== 'accounts'
            && $method === 'POST'
            && $uri === $createApp
        ) {
            return $this->response->json('Wrong database. Should be accounts.', 401);
        }

        // If route is to create application and database is accounts allow user to create
        if ($formattedAppName === 'accounts'
            && $method === 'POST'
            && $uri === $createApp
        ) {
            return $next($request);
        }

        if ($formattedAppName !== 'accounts') {
            if (!in_array($appName, $userCheck->applications)) {
                return $this->respond('tymon.jwt.absent', ['Profile does not exist for this application.'], 403);
            }

            GenericModel::setCollection('profiles');
            if (GenericModel::where('_id', '=', $userCheck->_id) === null) {
                return $this->respond('tymon.jwt.absent', ['Profile does not exist for this application.'], 403);
            }
        }

        return $next($request);
    }
}
