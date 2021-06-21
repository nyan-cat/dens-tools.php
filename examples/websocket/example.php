<?php

require_once __DIR__ . '/../../src/websocket/server.php';

class Server extends \DensTools\WebSocket\Server
{
    function __construct($interface, $port, $options)
    {
        parent::__construct($interface, $port, $options);
    }
}

$server = new Server('0.0.0.0', 37752,
[
    'swoole' =>
    [
        'worker_num'      => 4,
        'task_worker_num' => 4
    ],
    'session' =>
    [
        'lifetime' => 3600,
        'cookie' =>
        [
            'name'     => 'UserID',
            'expire'   => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly' => true,
            'samesite' => '',
            'priority' => ''
        ]
    ]
]);

$server->start(function()
{
});

$server->open(function($sender, $request)
{
});

$server->close(function($sender)
{
});

$schema = (object)
[
    'type' => 'object',
    'properties' => (object)
    [
        'name' => (object)
        [
            'type' => 'string'
        ],
        'value' => (object)
        [
            'type' => 'string'
        ]
    ],
    'required' => ['name', 'value']
];

$server->on('put', $schema, function($sender, $data)
{
    $sender[$data->name] = $data->value;
});

$server->on('get', function($sender, $data)
{
    $value = $sender[$data->name];
    $sender->post('value', $value);
});

$server->run();

?>