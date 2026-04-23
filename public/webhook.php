<?php

/**
 * BotWA "Cimol" - Webhook Endpoint
 * 
 * This file receives webhook POST requests from WAHA
 * and processes incoming WhatsApp messages.
 * 
 * URL: https://yourdomain.com/webhook.php
 */

// Bootstrap the application
require_once __DIR__ . '/../src/Bootstrap.php';
\BotWA\Bootstrap::init();

use BotWA\Logger;
use BotWA\Config;
use BotWA\MessageHandler;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET request = health check
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'bot' => 'Cimol',
            'version' => '1.0.0',
            'timestamp' => date('c'),
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verify webhook secret (if configured)
$webhookSecret = Config::get('waha_webhook_secret', '');
if (!empty($webhookSecret)) {
    $providedSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_GET['secret'] ?? '';
    if ($providedSecret !== $webhookSecret) {
        Logger::warning("Webhook secret mismatch", [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// Read the raw POST body
$rawBody = file_get_contents('php://input');

if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

// Parse JSON
$payload = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    Logger::error("Invalid JSON in webhook: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

Logger::debug("Webhook received", [
    'event' => $payload['event'] ?? 'unknown',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);

// Respond immediately to WAHA (don't make it wait)
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'received']);

// Flush output so WAHA gets the response immediately
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_end_flush();
    flush();
}

// Now process the message in the background
try {
    $handler = new MessageHandler();
    $handler->handle($payload);
} catch (\Throwable $e) {
    Logger::error("Webhook processing failed: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
