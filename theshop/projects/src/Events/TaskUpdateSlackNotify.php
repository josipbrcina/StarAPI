<?php

namespace TheShop\Projects\Events;

use App\GenericModel;
use Illuminate\Queue\SerializesModels;
use App\Events\Event;

/**
 * Class TaskUpdateSlackNotify
 * @package App\Events
 */
class TaskUpdateSlackNotify extends Event
{
    use SerializesModels;

    public $model;

    /**
     * TaskUpdateSlackNotify constructor.
     * @param GenericModel $model
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
