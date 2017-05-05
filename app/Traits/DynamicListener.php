<?php

namespace App\Traits;

use App\GenericModel;
use Illuminate\Support\Facades\App;

trait DynamicListener
{
    public function triggerListeners($event, $action)
    {
        $app = App::getFacadeRoot();
        $dispatcher = $app->events;

        $presetCollection = GenericModel::getCollection();

        $listenerRules = GenericModel::whereTo('listener-rules')
            ->all();

        $eventsListeners = [];

        foreach ($listenerRules as $rule) {
            if (!empty($rule->resource) && $rule->resource === $presetCollection && !empty($rule->event) &&
                $rule->event === $action
            ) {
                foreach ($rule->listeners as $events => $listeners) {
                    $eventsListeners[$events] = $listeners;
                }
            }
        }

        foreach ($eventsListeners as $eventName => $listenersArray) {
            foreach ($listenersArray as $listener) {
                $dispatcher->listen($eventName, $listener);
                event(new $eventName($event->model));
            }
        }
    }
}
