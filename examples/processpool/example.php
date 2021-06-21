<?php

include __DIR__ . '/../../vendor/autoload.php';

$pool = new \DensTools\ProcessPool\ProcessPool(16);

for($n = 0; $n < 50; ++$n)
{
    $pool->submit('php ./task.php', function($data)
    {
        echo 'Task response: ' . $data . PHP_EOL;
    });
}

$pool->run();

?>