<?php

namespace App\Services;

use Mookofe\Tail\Tail;

/**
 * Class RabbitMQ
 * @package App\Services
 */
class RabbitMQ
{
    public static function addTask($queue, $payload)
    {
        $jsonPayload = json_encode($payload);
        $message = new Tail();
        $message->add($queue, $jsonPayload);

        return true;
    }
}
