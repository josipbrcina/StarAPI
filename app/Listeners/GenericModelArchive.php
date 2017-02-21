<?php

namespace App\Listeners;

use App\Traits\DynamicListener;

class GenericModelArchive
{
    use DynamicListener;

    /**
     * Handle the event.
     * @param \App\Events\GenericModelArchive $event
     */
    public function handle(\App\Events\GenericModelArchive $event)
    {
        $action = 'archive';
        $this->triggerListeners($event, $action);
    }
}
