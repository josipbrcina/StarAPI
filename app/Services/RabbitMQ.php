<?php

namespace App\Services;

use Mookofe\Tail\Facades\Tail;

/**
 * Class RabbitMQ
 * @package App\Services
 */
class RabbitMQ
{
    public static function addTask($queue, $payload)
    {
        try {
            Tail::add($queue, $payload);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 400);
        }

        return response()->json(["messages" => ["Task successfully added to RabbitMQ queue."]], 200);
    }
}
