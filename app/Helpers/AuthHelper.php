<?php

namespace App\Helpers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Class AuthHelper
 * @package App\Helpers
 */
class AuthHelper
{
    /**
     * @param null $connectionName
     * @return bool
     */
    public static function setDatabaseConnection($connectionName = null)
    {
        $defaultDb = Config::get('database.default');
        if ($connectionName === null) {
            $connectionName = 'accounts';
        }
        Config::set('database.connections.'. $defaultDb . '.database', strtolower($connectionName));
        DB::purge($defaultDb);
        DB::connection($defaultDb);

        return true;
    }

    /**
     * Get authenticated user model
     * @return mixed
     */
    public static function getAuthenticatedUser($jwtAuthenticated = true)
    {
        $app = App::getFacadeRoot();

        if ($jwtAuthenticated) {
            return $app->authenticatedUser;
        }

        return false;
    }
}
