<?php
declare(strict_types=1);

namespace App\Services\Billing;

use App\Exceptions\ValidationException;

final class MercadoPagoGateway
{
    private array $config;

    public function __construct()
    {
        $payments = config('payments');
        $this->config = is_array($payments['mercado_pago'] ?? null) ? $payments['mercado_pago'] : [];
    }

    public function isConfigured(): bool
    {
        $token = $this->accessToken();
        return $token !== '' && stripos($token, 'COLE_AQUI') === false;
    }

    public function createPixPayment(array $payload): array
    {
        return $this->request('POST', '/v1/payments', $payload);
    }

    public function createSubscription(array $payload): array
    {
        return $this->request('POST', '/preapproval', $payload);
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->request('GET', '/preapproval/' . rawurlencode($subscriptionId));
    }

    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/v1/payments/' . rawurlencode($paymentId));
    }

    public function validateWebhookSignature(array $server, array $query): bool
    {
        $secret = trim((string) ($this->config['webhook_secret'] ?? ''));
        if ($secret === '' || stripos($secret, 'COLE_AQUI') !== false) {
            return false;
        }

        $xSignature = trim((string) ($server['HTTP_X_SIGNATURE'] ?? ''));
        $xRequestId = trim((string) ($server['HTTP_X_REQUEST_ID'] ?? ''));
        $dataId = trim((string) ($query['data.id'] ?? ''));
        if ($xSignature === '' || $xRequestId === '' || $dataId === '') {
            return false;
        }

        $parts = array_filter(array_map('trim', explode(',', $xSignature)));
        $ts = null;
        $hash = null;
        foreach ($parts as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2) {
                continue;
            }

            if ($pair[0] === 'ts') {
                $ts = trim($pair[1]);
            }
            if ($pair[0] === 'v1') {
                $hash = trim($pair[1]);
            }
        }

        if ($ts === null || $hash === null) {
            return false;
        }

        $manifest = 'id:' . $dataId . ';request-id:' . $xRequestId . ';ts:' . $ts . ';';
        $sha = hash_hmac('sha256', $manifest, $secret);
        return hash_equals($sha, $hash);
    }

    public function checkoutBaseUrl(): string
    {
        return trim((string) ($this->config['base_url'] ?? 'https://api.mercadopago.com'));
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!$this->isConfigured()) {
            throw new ValidationException('Mercado Pago nao configurado. Preencha o access token no ambiente.');
        }

        if (!function_exists('curl_init')) {
            throw new ValidationException('cURL nao esta habilitado no PHP para integrar com o gateway.');
        }

        $url = rtrim((string) ($this->config['base_url'] ?? 'https://api.mercadopago.com'), '/') . $path;
        $headers = [
            'Authorization: Bearer ' . $this->accessToken(),
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $env = strtolower(trim((string) ($this->config['env'] ?? '')));
        if ($env === 'sandbox' || $env === 'stage') {
            $headers[] = 'X-scope: stage';
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new ValidationException('Falha ao iniciar conexao com o gateway.');
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $caFile = $this->resolveCaFile();
        if ($caFile !== null) {
            curl_setopt($curl, CURLOPT_CAINFO, $caFile);
        }

        if ($payload !== null) {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new ValidationException('Falha ao serializar payload do gateway.');
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }

        $raw = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($raw === false || $error !== '') {
            throw new ValidationException('Falha ao comunicar com o Mercado Pago: ' . ($error !== '' ? $error : 'sem resposta'));
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new ValidationException('Resposta invalida recebida do Mercado Pago.');
        }

        if ($statusCode >= 400) {
            $message = trim((string) ($decoded['message'] ?? $decoded['error'] ?? 'Erro desconhecido no gateway.'));
            $cause = $decoded['cause'][0]['description'] ?? null;
            if (is_string($cause) && trim($cause) !== '') {
                $message .= ' ' . trim($cause);
            }
            throw new ValidationException($message);
        }

        return $decoded;
    }

    private function accessToken(): string
    {
        return trim((string) ($this->config['access_token'] ?? ''));
    }

    private function resolveCaFile(): ?string
    {
        $candidates = [
            trim((string) ($this->config['ssl_cafile'] ?? '')),
            trim((string) ini_get('curl.cainfo')),
            trim((string) ini_get('openssl.cafile')),
            'D:/wamp64/bin/php/certs/cacert.pem',
            'D:\\wamp64\\bin\\php\\certs\\cacert.pem',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
