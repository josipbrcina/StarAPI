<?php

namespace App\Events;

use App\Events\Event;
use App\GenericModel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TaskStatusHistory extends Event
{
    use SerializesModels;

    public $model;

    /**
     * TaskStatusHistory constructor.
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
