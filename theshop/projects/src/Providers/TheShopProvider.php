<?php

namespace TheShop\Projects\Providers;

use Illuminate\Support\ServiceProvider;
use TheShop\Projects\Console\Commands\EmailProfilePerformance;
use TheShop\Projects\Console\Commands\MonthlyMinimumCheck;
use TheShop\Projects\Console\Commands\NotifyAdminsTaskDeadline;
use TheShop\Projects\Console\Commands\SprintReminder;
use TheShop\Projects\Console\Commands\UnfinishedTasks;

class TheShopProvider extends ServiceProvider
{
    protected $commands = [
        EmailProfilePerformance::class,
        MonthlyMinimumCheck::class,
        NotifyAdminsTaskDeadline::class,
        SprintReminder::class,
        UnfinishedTasks::class
    ];

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

        if (! $this->app->routesAreCached()) {
            require __DIR__.'../routes.php';
        }

        $this->publishes([
            realpath(__DIR__.'../migrations') => $this->app->databasePath().'/migrations',
        ]);

        $this->publishes([
            realpath(__DIR__.'../seeds') => $this->app->databasePath().'/seeds',
        ]);

        $this->commands($this->commands);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
