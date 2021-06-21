<?php

namespace DensTools\WebSocket;

class Handler
{
    function __construct($callback, $schema = null)
    {
        $this->callback = $callback;

        if($schema !== null)
        {
            $this->schema = \Swaggest\JsonSchema\Schema::import($schema);
        }
    }

    function invoke($sender, $data, $context)
    {
        if($this->schema !== null)
        {
            $this->schema->in($data);
        }

        $this->callback->bindTo($context, $context)($sender, $data);
    }

    private $callback;
    private $schema;
}

?>