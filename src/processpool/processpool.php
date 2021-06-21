<?php

namespace DensTools\ProcessPool;

require_once __DIR__ . '/exception.php';

class ProcessPool
{
    function __construct($size)
    {
        $this->size = $size;
    }

    function submit($task, $handler)
    {
        $key = $this->key++;
        $this->tasks[$key] = $task;
        $this->handlers[$key] = $handler;
    }

    function run($quota = 0, $shuffle = false)
    {
        $start = time();

        if(empty($this->tasks))
        {
            return;
        }

        if($shuffle)
        {
            $this->shuffle();
        }

        do
        {
            $this->load();
            $this->await();
            $this->read();

            if($quota && ((time() - $start) > $quota))
            {
                break;
            }
        }
        while(!empty($this->tasks) || !empty($this->procs));

        while(!empty($this->procs))
        {
            $this->await();
            $this->read();
        }

        return count($this->tasks);
    }

    private function load()
    {
        if(count($this->procs) >= $this->size)
        {
            return;
        }

        foreach($this->tasks as $key => $task)
        {
            $this->procs[$key] = popen($task, 'r');
            stream_set_blocking($this->procs[$key], false);
            $this->responses[$key] = '';

            unset($this->tasks[$key]);

            if(count($this->procs) >= $this->size)
            {
                return;
            }
        }
    }

    private function await()
    {
        do
        {
            $read = array_values($this->procs);
            $write = [];
            $except = [];
            $count = stream_select($read, $write, $except, 0, 10000);

            if($count === false)
            {
                throw new Exception('ProcessPool stream_select error');
            }
        }
        while(!$count);
    }

    private function read()
    {
        foreach($this->procs as $key => $proc)
        {
            if(feof($proc))
            {
                pclose($proc);
                $this->handlers[$key]($this->responses[$key]);
                unset($this->handlers[$key]);
                unset($this->procs[$key]);
                unset($this->responses[$key]);
            }
            else
            {
                $this->responses[$key] .= fread($proc, 65536);
            }
        }
    }

    private function shuffle()
    {
        $keys = array_keys($this->tasks);
        shuffle($keys);
        $shuffled = [];

        foreach($keys as $key)
        {
            $shuffled[$key] = $this->tasks[$key];
        }

        $this->tasks = $shuffled;
    }

    private $size;

    private $key = 0;

    private $tasks = [];
    private $handlers = [];
    private $procs = [];
    private $responses = [];
};

?>