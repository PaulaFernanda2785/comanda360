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
    public function __construct(
        private readonly SubscriptionGatewayService $gatewayService = new SubscriptionGatewayService()
    ) {}

    public function mercadoPago(Request $request): Response
    {
        try {
            $rawBody = file_get_contents('php://input');
            $body = json_decode(is_string($rawBody) ? $rawBody : '', true);
            $payload = is_array($body) ? $body : [];

            $merged = $request->query;
            if (!isset($merged['type']) && isset($payload['type'])) {
                $merged['type'] = (string) $payload['type'];
            }
            if (!isset($merged['data.id']) && isset($payload['data']['id'])) {
                $merged['data.id'] = (string) $payload['data']['id'];
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
}
