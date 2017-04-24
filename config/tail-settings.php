<?php
return [

    'default' => 'default_connection',

    'connections' => [

        'default_connection' => [
            'host' => '192.168.33.10',
            'port' => 5672,
            'username' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'exchange' => 'default_exchange_name',
            'consumer_tag' => 'consumer',
            'exchange_type' => 'direct',
            'content_type' => 'text/plain'
        ],
        'other_server' => [
            'host' => '192.168.0.10',
            'port' => 5672,
            'username' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'exchange' => 'default_exchange_name',
            'consumer_tag' => 'consumer',
            'exchange_type' => 'fanout',
            'content_type' => 'application/json'
        ],
    ],
];
