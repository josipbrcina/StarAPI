<?php

namespace App\Events;

use App\GenericModel;
use Illuminate\Queue\SerializesModels;

/**
 * Class TaskBlockedNotifyProjectOwner
 * @package App\Events
 */
class TaskBlockedNotifyProjectOwner extends Event
{
    use SerializesModels;

    public $model;

    /**
     * TaskBlockedNotifyProjectOwner constructor.
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
