<?php

namespace DensTools\WebSocket;

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/handler.php';

class Server
{
    function __construct($interface, $port, $options)
    {
        $this->options = $options;
        $this->server = new \Swoole\WebSocket\Server($interface, $port);

        if(isset($options['swoole']))
        {
            $this->server->set($options['swoole']);
        }

        $this->server->on('handshake', (function(\Swoole\HTTP\Request $request, \Swoole\HTTP\Response $response)
        {
            $secWebSocketKey = $request->header['sec-websocket-key'];
            $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

            if(preg_match($patten, $secWebSocketKey) === 0 || strlen(base64_decode($secWebSocketKey)) !== 16)
            {
                $response->end();
                return false;
            }

            $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            $headers =
            [
                'Upgrade'               => 'websocket',
                'Connection'            => 'Upgrade',
                'Sec-WebSocket-Accept'  => $key,
                'Sec-WebSocket-Version' => '13',
            ];

            if(isset($request->header['sec-websocket-protocol']))
            {
                $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
            }

            foreach($headers as $key => $val)
            {
                $response->header($key, $val);
            }

            $session = $this->options['session'];
            $cookie = $session['cookie'];

            $id = null;

            if(isset($request->cookie[$cookie['name']]))
            {
                $id = $request->cookie[$cookie['name']];

                if(!isset($this->sessions[$id]))
                {
                    $id = null;
                }
            }

            if($id === null)
            {
                $id = random_bytes(32);
                $response->cookie($cookie['name'], $id, $cookie['expire'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly'], $cookie['samesite'], $cookie['priority']);
                $this->sessions[$id] = new \DensTools\WebSocket\Session($this->server);
            }

            $this->sessions[$id]->connect($request->fd);
            $this->online[$request->fd] = $this->sessions[$id];

            $response->status(101);
            $response->end();
            return true;
        })->bindTo($this, $this));

        $this->server->on('message', (function(\Swoole\WebSocket\Server $server, \Swoole\WebSocket\Frame $frame)
        {
            if($frame->opcode == WEBSOCKET_OPCODE_TEXT)
            {
                $object = json_decode($frame->data);
                $message = $object->type;

                if(isset($this->subscribers[$message]))
                {
                    $sender = $this->online[$frame->fd];
                    $data = $object->data;

                    foreach($this->subscribers[$message] as $handler)
                    {
                        try
                        {
                            $handler->invoke($sender, $data, $this);
                        }
                        catch(Exception $e)
                        {
                        }
                    }
                }
            }
        })->bindTo($this, $this));

        $this->server->on('task', (function(\Swoole\WebSocket\Server $server, $task_id, $reactorId, $data)
        {
            $closure = unserialize($data)->getClosure();
            return $closure();
        })->bindTo($this, $this));

        $this->server->on('close', (function(\Swoole\WebSocket\Server $server, int $fd)
        {
            $sender = $this->online[$fd];

            if($this->close !== null)
            {
                ($this->close)($sender);
            }

            $sender->disconnect();
            unset($this->online[$fd]);
        })->bindTo($this, $this));

        $this->server->tick(10000, (function()
        {
            $this->purge();
        })->bindTo($this, $this));
    }

    function start($callback)
    {
        $callback = $callback->bindTo($this, $this);

        $this->server->on('start', function(\Swoole\WebSocket\Server $server) use($callback)
        {
            $callback();
        });
    }

    function open($callback)
    {
        $callback = $callback->bindTo($this, $this);

        $this->server->on('open', (function(\Swoole\WebSocket\Server $server, \Swoole\Http\Request $request) use($callback)
        {
            $callback($this->online[$request->fd], $request);
        })->bindTo($this, $this));
    }

    function close($callback)
    {
        $this->close = $callback->bindTo($this, $this);
    }

    function on($message, $param1, $param2 = null)
    {
        if(!isset($this->subscribers[$message]))
        {
            $this->subscribers[$message] = [];
        }

        if(is_callable($param1))
        {
            $schema = $param2;
            $callback = $param1;
        }
        else
        {
            $schema = $param1;
            $callback = $param2;
        }

        $this->subscribers[$message][] = new Handler($callback, $schema);
    }

    function broadcast($message, $data)
    {
        foreach($this->online as $session)
        {
            $session->post($message, $data);
        }
    }

    function submit($task, $consumer = null)
    {
        $closure = new \Opis\Closure\SerializableClosure($task->bindTo($this, $this));
        $serialized = serialize($closure);

        if($consumer !== null)
        {
            $consumer = $consumer->bindTo($this, $this);

            $task_id = $this->server->task($serialized, -1, function(\Swoole\WebSocket\Server $server, $task_id, $data) use($consumer)
            {
                $consumer($data);
            });
        }
        else
        {
            $task_id = $this->server->task($serialized);
        }
    }

    function run()
    {
        $this->server->start();
    }

    function stop()
    {
        $this->server->stop();
    }

    private function purge()
    {
        $lifetime = $this->options['session']['lifetime'];

        foreach($this->sessions as $id => $session)
        {
            if($session->idleFor() >= $lifetime)
            {
               unset($this->sessions[$id]);
            }
        }
    }

    private $options;
    private $server;
    private $sessions = [];
    private $online = [];
    private $subscribers = [];

    private $close = null;
}

?>