<?php

namespace App\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class TheShopEventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\GenericModelCreate' => [
            'App\Listeners\GenericModelCreate'
        ],
        'App\Events\GenericModelUpdate' => [
            'App\Listeners\GenericModelUpdate'
        ],
        'App\Events\GenericModelDelete' => [
            'App\Listeners\GenericModelDelete'
        ],
        'App\Events\GenericModelArchive' => [
            'App\Listeners\GenericModelArchive'
        ],
        'App\Events\ProfileUpdate' => [
            'App\Listeners\ProfileUpdate'
        ]
    ];

    /**
     * Register any other events for your application.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher $events
     * @return void
     */
    public function boot(DispatcherContract $events)
    {
        parent::boot($events);

        //
    }
}
