<?php

namespace DensTools\WebSocket;

class Session implements \ArrayAccess
{
    function __construct($server)
    {
        $this->server = $server;
    }

    function offsetSet($offset, $value)
    {
        $this->values[$offset] = $value;
    }

    function offsetExists($offset)
    {
        return isset($this->values[$offset]);
    }

    function offsetUnset($offset)
    {
        unset($this->values[$offset]);
    }

    function offsetGet($offset)
    {
        return $this->values[$offset] ?? null;
    }

    function idleFor()
    {
        return 0;
    }

    function connect($fd)
    {
        $this->fd = $fd;
    }

    function disconnect()
    {
        $this->fd = null;
    }

    function post($message, $data)
    {
        $this->server->push($this->fd, self::createMessage($message, $data));
    }

    private static function createMessage($message, $data)
    {
        return json_encode(['type' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    }

    private $server;
    private $fd;
    private $values = [];
}

?>