<?php

namespace TheShop\Projects\Events;

use App\Events\Event;
use App\GenericModel;
use Illuminate\Queue\SerializesModels;

class ModelUpdate extends Event
{
    use SerializesModels;

    public $model;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(GenericModel $model)
    {
        $this->model = $model;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
