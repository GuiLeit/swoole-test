<?php
declare(strict_types=1);

use OpenSwoole\Http\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

// Create HTTP server for webhook
$server = new Server("0.0.0.0", 8080);

// Redis connection
$redis = new Redis();

$server->on('start', function ($server) use ($redis) {
    echo "Webhook server started on port 8080\n";
    
    // Initialize Redis connection
    if (!$redis->connect('redis', 6379)) {
        echo "Failed to connect to Redis\n";
        exit(1);
    }
    echo "Connected to Redis\n";
});

$server->on('request', function (Request $request, Response $response) use ($redis) {
    // Set CORS headers
    $response->header('Access-Control-Allow-Origin', '*');
    $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    
    // Handle preflight OPTIONS request
    if ($request->server['request_method'] === 'OPTIONS') {
        $response->status(200);
        $response->end();
        return;
    }
    
    // Handle webhook POST requests
    if ($request->server['request_method'] !== 'POST') {
        
        $response->status(200);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode(['status' => 'Webhook server is running', 'port' => 8080]));
        return;
    }

    try {
        // Get the raw body
        $body = $request->getContent();
        $data = json_decode($body, true);
        
        if (!$data) {
            $data = ['message' => $body, 'raw' => true];
        }
        
        // Add timestamp
        $webhookMessage = [
            'type' => 'webhook',
            'timestamp' => time(),
            'data' => $data,
            'source' => $request->header['user-agent'] ?? 'unknown'
        ];
        
        // Save to Redis with a key
        $messageId = 'webhook_' . time() . '_' . rand(1000, 9999);
        $redis->setex($messageId, 3600, json_encode($webhookMessage)); // Expire after 1 hour
        
        echo "Received webhook: " . json_encode($webhookMessage) . "\n";
        
        $response->status(200);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode(['status' => 'success', 'message_id' => $messageId]));
        
    } catch (Exception $e) {
        echo "Error processing webhook: " . $e->getMessage() . "\n";
        
        $response->status(500);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
    }
});

$server->start();

