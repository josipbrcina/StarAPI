<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Artisan;

/**
 * Class DatabaseSeeder
 * @package App\Helpers
 */
class DatabaseSeeder
{
    /**
     * @return bool
     */
    public static function seedApplicationDatabase()
    {
        Artisan::call('db:seed');

        return true;
    }
}
