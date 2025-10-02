<?php
declare(strict_types=1);

use OpenSwoole\WebSocket\Server;

// $server = new Server('0.0.0.0', 9501);
$server = new OpenSwoole\WebSocket\Server("0.0.0.0", 9501);

$server->on('request', function ($req, $resp) {
    $resp->header('Content-Type', 'text/plain');
    $resp->end("Hello from PHP 8.4 + Swoole at " . date('H:i:s'));
});

$server->on('message', function ($server, $frame) {
    echo "Received message: {$frame->data}\n";
    
    $connections = $server->connections;

    foreach ($connections as $fd) {
        if ($fd != $frame->fd) {
            $server->push($fd, json_encode([
                'from' => $frame->fd,
                'message' => $frame->data
            ]));
        }
    }
});

echo "Swoole listening on port 9501\n";
$server->start();