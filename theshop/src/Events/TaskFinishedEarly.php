<?php

namespace TheShop\Src\Events;

use App\GenericModel;
use Illuminate\Queue\SerializesModels;
use App\Events\Event;

class TaskFinishedEarly extends Event
{
    use SerializesModels;

    public $model;

    /**
     * TaskFinishedEarly constructor.
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
