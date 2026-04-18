<?php
declare(strict_types=1);

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$rawBody = file_get_contents('php://input');
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (!is_string($value)) {
        continue;
    }

    if (str_starts_with($key, 'HTTP_') || in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'REQUEST_METHOD', 'REQUEST_URI'], true)) {
        $headers[$key] = $value;
    }
}

$line = json_encode([
    'at' => date('c'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'get' => $_GET,
    'post' => $_POST,
    'raw' => is_string($rawBody) ? $rawBody : '',
    'headers' => $headers,
], JSON_UNESCAPED_SLASHES);

if ($line !== false) {
    file_put_contents($logDir . '/webhook_probe.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

http_response_code(200);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'ok' => true,
    'message' => 'probe reached',
], JSON_UNESCAPED_SLASHES);
