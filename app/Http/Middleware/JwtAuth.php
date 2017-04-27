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

        $coreDatabaseName = Config::get('sharedSettings.internalConfiguration.coreDatabaseName');
        // If user is admin and request route is core database, set connection and allow admins to write into database
        if ($userCheck->admin === true && $coreDatabaseName === strtolower($request->route('appName'))) {
            AuthHelper::setDatabaseConnection($coreDatabaseName);
            return $next($request);
        }

        // Set database connection to request "appName"
        AuthHelper::setDatabaseConnection($request->route('appName'));

        /*Check account applications to see if he is registered to requested app and check application profiles to
        see if there is profile related to that account*/
        $method = $request->method();
        $url = $request->url();
        $joinApp = '/application/join';
        $leaveApp = '/application/leave';
        $createApp = 'application/create';

        // Allow only "join application" route otherwise do validation
        if ($request->route('appName') !== 'accounts'
            && $method === 'POST'
            && (strlen($url) - strlen($joinApp) === strpos($url, $joinApp)
                || strlen($url) - strlen($leaveApp) === strpos($url, $leaveApp)
                || strlen($url) - strlen($createApp) === strpos($url, $createApp))
        ) {
            return $next($request);
        }

        if (!in_array($request->route('appName'), $userCheck->applications)) {
            return $this->respond('tymon.jwt.absent', ['Profile does not exist for this application.'], 401);
        }

        GenericModel::setCollection('profiles');
        if (GenericModel::where('_id', '=', $userCheck->_id) === null) {
            return $this->respond('tymon.jwt.absent', ['Profile does not exist for this application.'], 401);
        }

        return $next($request);
    }
}
