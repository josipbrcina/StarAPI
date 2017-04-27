<?php

namespace App\Http\Middleware;

use App\Account;
use App\GenericModel;
use App\Helpers\AuthHelper;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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

        $coreDatabaseName = \Config::get('sharedSettings.internalConfiguration.coreDatabaseName');
        // If user is admin and request route is core database, set connection and allow admins to write into database
        if ($userCheck->admin === true && $coreDatabaseName === strtolower($request->route('appName'))) {
            $defaultDb = Config::get('database.default');
            Config::set('database.connections.mongodb.database', $coreDatabaseName);
            DB::purge($defaultDb);
            DB::connection($defaultDb);
            return $next($request);
        }

        GenericModel::setCollection('profiles');
        if (!in_array($request->route('appName'), $userCheck->applications)) {
            return $this->respond('tymon.jwt.absent', ['Profile does not exist for this application.'], 401);
        }
        if (GenericModel::where('_id', '=', $userCheck->_id) === null) {
            return $this->respond('tymon.jwt.absent', ['Profile does not exist for this application.'], 401);
        }

        AuthHelper::setDatabaseConnection($request->route('appName'));

        return $next($request);
    }
}
