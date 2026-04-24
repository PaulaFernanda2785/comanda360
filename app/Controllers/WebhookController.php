<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Admin\SubscriptionGatewayService;

final class WebhookController extends Controller
{
    private const MAX_WEBHOOK_BODY_BYTES = 131072;

    public function __construct(
        private readonly SubscriptionGatewayService $gatewayService = new SubscriptionGatewayService()
    ) {}

    public function mercadoPago(Request $request): Response
    {
        $contentLength = (int) ($request->server['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > self::MAX_WEBHOOK_BODY_BYTES) {
            return Response::make(json_encode([
                'ok' => false,
                'message' => 'Payload excede o limite permitido.',
            ], JSON_UNESCAPED_SLASHES) ?: '{"ok":false}', 413, [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
        }

        $rawBody = file_get_contents('php://input');
        if (is_string($rawBody) && strlen($rawBody) > self::MAX_WEBHOOK_BODY_BYTES) {
            return Response::make(json_encode([
                'ok' => false,
                'message' => 'Payload excede o limite permitido.',
            ], JSON_UNESCAPED_SLASHES) ?: '{"ok":false}', 413, [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
        }

        $body = json_decode(is_string($rawBody) ? $rawBody : '', true);
        $payload = is_array($body) ? $body : [];
        $merged = $request->query;
        if (!isset($merged['type']) && isset($payload['type'])) {
            $merged['type'] = (string) $payload['type'];
        }
        if (!isset($merged['data.id']) && isset($payload['data']['id'])) {
            $merged['data.id'] = (string) $payload['data']['id'];
        }

        $this->logAttempt($request, $payload, $merged);

        try {
            if ($payload === []
                && $merged === []
                && trim((string) ($request->server['HTTP_X_SIGNATURE'] ?? '')) === '') {
                return Response::make(json_encode([
                    'ok' => true,
                    'message' => 'Webhook endpoint reachable.',
                ], JSON_UNESCAPED_SLASHES) ?: '{"ok":true}', 200, [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ]);
            }

            $result = $this->gatewayService->processWebhook($merged, $request->server);
            return Response::make(json_encode([
                'ok' => true,
                'type' => $result['type'] ?? null,
                'data_id' => $result['data_id'] ?? null,
            ], JSON_UNESCAPED_SLASHES) ?: '{"ok":true}', 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
        } catch (ValidationException $e) {
            return Response::make(json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES) ?: '{"ok":false}', 400, [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
        }
    }

    private function logAttempt(Request $request, array $payload, array $merged): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $line = json_encode([
            'at' => date('c'),
            'method' => $request->method,
            'uri' => $request->uri,
            'query' => $request->query,
            'payload' => $this->truncateForLog($payload),
            'merged' => $merged,
            'headers' => [
                'x_signature' => (string) ($request->server['HTTP_X_SIGNATURE'] ?? ''),
                'x_request_id' => (string) ($request->server['HTTP_X_REQUEST_ID'] ?? ''),
                'content_type' => (string) ($request->server['CONTENT_TYPE'] ?? ''),
                'user_agent' => (string) ($request->server['HTTP_USER_AGENT'] ?? ''),
            ],
        ], JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            $line = '{"at":"' . date('c') . '","error":"log_encode_failed"}';
        }

        file_put_contents($dir . '/mercado_pago_webhook.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function truncateForLog(mixed $value): mixed
    {
        if (is_string($value)) {
            return strlen($value) > 2000 ? substr($value, 0, 2000) . '...[truncated]' : $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->truncateForLog($item);
        }

        return $normalized;
    }
}
