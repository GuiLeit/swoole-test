<?php
declare(strict_types=1);

use OpenSwoole\WebSocket\Server;
use OpenSwoole\Timer;

$server = new OpenSwoole\WebSocket\Server("0.0.0.0", 9501);

// Redis connection for checking messages
$redis = new Redis();

$server->on('start', function ($server) use ($redis) {
    echo "WebSocket server started on port 9501\n";
    
    // Connect to Redis
    if (!$redis->connect('redis', 6379)) {
        echo "Failed to connect to Redis\n";
        exit(1);
    }
    echo "Connected to Redis\n";
    
    // Use timer to poll Redis for new messages instead of pub/sub
    Timer::tick(1000, function () use ($server, $redis) {
        try {
            // Get all webhook message keys
            $keys = $redis->keys('webhook_*');
            
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    // Check if this message was already processed
                    $processedKey = $key . ':processed';
                    if (!$redis->exists($processedKey)) {
                        // Get the message
                        $message = $redis->get($key);
                        if ($message) {
                            echo "Processing Redis message: $message\n";
                            
                            // Send message to all connected WebSocket clients
                            $connections = $server->connections;
                            if (!empty($connections)) {
                                foreach ($connections as $fd) {
                                    $server->push($fd, json_encode([
                                        'type' => 'webhook_message',
                                        'data' => json_decode($message, true)
                                    ]));
                                }
                            }
                            
                            // Mark as processed (expires in 1 hour)
                            $redis->setex($processedKey, 3600, '1');
                        }
                    }
                }
            }
        } catch (Exception $e) {
            echo "Error polling Redis: " . $e->getMessage() . "\n";
        }
    });
});

$server->on('request', function ($req, $resp) {
    $resp->header('Content-Type', 'text/plain');
    $resp->end("Hello from PHP + Swoole WebSocket Server at " . date('H:i:s'));
});

$server->on('open', function ($server, $request) {
    echo "New WebSocket connection: {$request->fd}\n";
    
    // Send welcome message
    $server->push($request->fd, json_encode([
        'type' => 'system',
        'message' => 'Connected to WebSocket server'
    ]));
});

$server->on('message', function ($server, $frame) {
    echo "Received WebSocket message: {$frame->data}\n";
    
    // Broadcast manual messages to other connected clients
    $connections = $server->connections;
    foreach ($connections as $fd) {
        if ($fd != $frame->fd) {
            $server->push($fd, json_encode([
                'type' => 'user_message',
                'from' => $frame->fd,
                'message' => $frame->data
            ]));
        }
    }
});

$server->on('close', function ($server, $fd) {
    echo "WebSocket connection closed: {$fd}\n";
});

echo "Starting Swoole WebSocket server...\n";
$server->start();