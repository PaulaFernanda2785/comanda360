<?php
declare(strict_types=1);

if (!function_exists('request_base_path')) {
    function request_base_path(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName === '') {
            return '';
        }

        $directory = trim(dirname($scriptName), "/\\.");
        return $directory !== '' ? '/' . $directory : '';
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $app = config('app');
        return rtrim($app['base_url'], '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('config')) {
    function config(string $file): array
    {
        $path = BASE_PATH . '/config/' . $file . '.php';
        if (!file_exists($path)) {
            throw new RuntimeException('Arquivo de configuração não encontrado: ' . $file);
        }
        return require $path;
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $basePath = request_base_path();
        $normalizedPath = ltrim($path, '/');

        if ($normalizedPath === '') {
            return $basePath !== '' ? $basePath . '/' : '/';
        }

        return ($basePath !== '' ? $basePath : '') . '/' . $normalizedPath;
    }
}

if (!function_exists('asset_url')) {
    function asset_url(?string $path): string
    {
        $value = trim((string) ($path ?? ''));
        if ($value === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return $value;
        }

        $normalizedPath = '/' . ltrim($value, '/');
        $basePath = str_replace('\\', '/', BASE_PATH);
        $documentRoot = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $publicPath = str_replace('\\', '/', BASE_PATH . '/public');

        if ($documentRoot !== '') {
            $docFile = $documentRoot . $normalizedPath;
            $docPublicFile = $documentRoot . '/public' . $normalizedPath;

            if (is_file($docFile)) {
                return base_url(ltrim($normalizedPath, '/'));
            }

            if (is_file($docPublicFile)) {
                return base_url('public/' . ltrim($normalizedPath, '/'));
            }

            if (rtrim($documentRoot, '/') === rtrim($publicPath, '/')) {
                return base_url(ltrim($normalizedPath, '/'));
            }

            if (rtrim($documentRoot, '/') === rtrim($basePath, '/')) {
                return base_url('public/' . ltrim($normalizedPath, '/'));
            }
        }

        return base_url(ltrim($normalizedPath, '/'));
    }
}

if (!function_exists('product_image_url')) {
    function product_image_url(?string $path): string
    {
        $value = trim((string) ($path ?? ''));
        if ($value === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return $value;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $value), '/');
        if (str_starts_with($normalizedPath, 'public/')) {
            $normalizedPath = ltrim(substr($normalizedPath, strlen('public/')), '/');
        }

        $isLegacyProductPath = str_starts_with($normalizedPath, 'uploads/products/');
        $isCompanyProductPath = preg_match('#^uploads/company/\d+/products/#', $normalizedPath) === 1;
        if (!$isLegacyProductPath && !$isCompanyProductPath) {
            return asset_url($value);
        }

        return base_url('/media/product?path=' . rawurlencode($normalizedPath));
    }
}

if (!function_exists('company_image_url')) {
    function company_image_url(?string $path): string
    {
        $value = trim((string) ($path ?? ''));
        if ($value === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return $value;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $value), '/');
        if (str_starts_with($normalizedPath, 'public/')) {
            $normalizedPath = ltrim(substr($normalizedPath, strlen('public/')), '/');
        }

        if (!str_starts_with($normalizedPath, 'uploads/company/')) {
            return asset_url($value);
        }

        return base_url('/media/company?path=' . rawurlencode($normalizedPath));
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = [], string $layout = 'layouts/app'): string
    {
        return \App\Core\View::render($template, $data, $layout);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to): \App\Core\Response
    {
        return \App\Core\Response::redirect($to);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        \App\Core\Session::start();

        $token = \App\Core\Session::get('_csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            \App\Core\Session::put('_csrf_token', $token);
        }

        return $token;
    }
}

if (!function_exists('session_idle_timeout_seconds')) {
    function session_idle_timeout_seconds(): int
    {
        $app = config('app');
        $seconds = (int) ($app['session_idle_timeout'] ?? 1800);

        return $seconds >= 60 ? $seconds : 1800;
    }
}

if (!function_exists('idempotency_token')) {
    function idempotency_token(string $scope = 'default'): string
    {
        \App\Core\Session::start();

        $token = bin2hex(random_bytes(24));
        $allTokens = \App\Core\Session::get('_idempotency_tokens', []);
        if (!is_array($allTokens)) {
            $allTokens = [];
        }

        if (!isset($allTokens[$scope]) || !is_array($allTokens[$scope])) {
            $allTokens[$scope] = [];
        }

        $allTokens[$scope][$token] = time();
        foreach ($allTokens as $savedScope => $tokensByScope) {
            if (!is_array($tokensByScope)) {
                unset($allTokens[$savedScope]);
                continue;
            }

            foreach ($tokensByScope as $savedToken => $createdAt) {
                if (!is_int($createdAt) || (time() - $createdAt) > 3600) {
                    unset($allTokens[$savedScope][$savedToken]);
                }
            }
        }

        \App\Core\Session::put('_idempotency_tokens', $allTokens);
        return $token;
    }
}

if (!function_exists('form_security_fields')) {
    function form_security_fields(string $scope = 'default'): string
    {
        $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        $idempotency = htmlspecialchars(idempotency_token($scope), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_csrf_token" value="' . $csrf . '">' .
            '<input type="hidden" name="_idempotency_token" value="' . $idempotency . '">' .
            '<input type="hidden" name="_form_scope" value="' . htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('validate_form_submission')) {
    function validate_form_submission(array $input, string $scope = 'default', int $duplicateWindowSeconds = 5): array
    {
        \App\Core\Session::start();

        $csrfFromRequest = trim((string) ($input['_csrf_token'] ?? ''));
        $idempotencyFromRequest = trim((string) ($input['_idempotency_token'] ?? ''));
        $scopeFromRequest = trim((string) ($input['_form_scope'] ?? ''));
        if ($scopeFromRequest !== '' && !hash_equals($scope, $scopeFromRequest)) {
            return [
                'ok' => false,
                'message' => 'Escopo de envio invalido para esta operacao.',
            ];
        }
        $effectiveScope = $scope;

        if ($csrfFromRequest === '' || $idempotencyFromRequest === '') {
            return [
                'ok' => false,
                'message' => 'Requisicao invalida. Atualize a pagina e tente novamente.',
            ];
        }

        $csrfInSession = (string) \App\Core\Session::get('_csrf_token', '');
        if ($csrfInSession === '' || !hash_equals($csrfInSession, $csrfFromRequest)) {
            return [
                'ok' => false,
                'message' => 'Sessao expirada ou token invalido. Atualize a pagina e envie novamente.',
            ];
        }

        $fingerprint = hash('sha256', $effectiveScope . '|' . $idempotencyFromRequest);
        $processed = \App\Core\Session::get('_processed_idempotency_tokens', []);
        if (!is_array($processed)) {
            $processed = [];
        }

        $now = time();
        foreach ($processed as $savedFingerprint => $processedAt) {
            if (!is_int($processedAt) || ($now - $processedAt) > 600) {
                unset($processed[$savedFingerprint]);
            }
        }

        if (isset($processed[$fingerprint]) && ($now - (int) $processed[$fingerprint]) <= $duplicateWindowSeconds) {
            return [
                'ok' => false,
                'message' => 'Requisicao ja recebida ha poucos segundos. Aguarde antes de tentar novamente.',
                'duplicate' => true,
            ];
        }

        $allTokens = \App\Core\Session::get('_idempotency_tokens', []);
        if (!is_array($allTokens) || !isset($allTokens[$effectiveScope]) || !is_array($allTokens[$effectiveScope])) {
            return [
                'ok' => false,
                'message' => 'Token de envio expirado. Recarregue a pagina antes de enviar novamente.',
            ];
        }

        if (!isset($allTokens[$effectiveScope][$idempotencyFromRequest])) {
            if (isset($processed[$fingerprint]) && ($now - (int) $processed[$fingerprint]) <= $duplicateWindowSeconds) {
                return [
                    'ok' => false,
                    'message' => 'Requisicao duplicada ignorada com seguranca.',
                    'duplicate' => true,
                ];
            }

            return [
                'ok' => false,
                'message' => 'Token de envio invalido ou ja utilizado. Atualize a pagina e tente novamente.',
            ];
        }

        unset($allTokens[$effectiveScope][$idempotencyFromRequest]);
        if ($allTokens[$effectiveScope] === []) {
            unset($allTokens[$effectiveScope]);
        }

        $processed[$fingerprint] = $now;

        \App\Core\Session::put('_idempotency_tokens', $allTokens);
        \App\Core\Session::put('_processed_idempotency_tokens', $processed);

        return [
            'ok' => true,
            'duplicate' => false,
        ];
    }
}

if (!function_exists('status_label')) {
    function status_label(string $context, mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '-';
        }

        $maps = [
            'order_status' => [
                'pending' => 'Pendente',
                'received' => 'Recebido',
                'preparing' => 'Em preparo',
                'ready' => 'Pronto',
                'delivered' => 'Entregue',
                'paid' => 'Pago',
                'finished' => 'Finalizado',
                'canceled' => 'Cancelado',
            ],
            'order_payment_status' => [
                'pending' => 'Pendente',
                'partial' => 'Parcial',
                'paid' => 'Pago',
                'canceled' => 'Cancelado',
            ],
            'order_channel' => [
                'table' => 'Mesa',
                'delivery' => 'Entrega',
                'pickup' => 'Retirada',
                'counter' => 'Balcao',
            ],
            'order_operational_flag' => [
                'paid_waiting_production' => 'Pago aguardando producao',
            ],
            'payment_status' => [
                'pending' => 'Pendente',
                'paid' => 'Pago',
                'failed' => 'Falhou',
                'refunded' => 'Estornado',
                'canceled' => 'Cancelado',
            ],
            'print_log_status' => [
                'success' => 'Sucesso',
                'failed' => 'Falha',
            ],
            'cash_register_status' => [
                'open' => 'Aberto',
                'closed' => 'Fechado',
            ],
            'command_status' => [
                'aberta' => 'Aberta',
                'fechada' => 'Fechada',
                'cancelada' => 'Cancelada',
            ],
            'table_status' => [
                'livre' => 'Livre',
                'ocupada' => 'Ocupada',
                'aguardando_fechamento' => 'Aguardando fechamento',
                'bloqueada' => 'Bloqueada',
            ],
            'delivery_status' => [
                'pending' => 'Pendente',
                'assigned' => 'Atribuida',
                'in_route' => 'Em rota',
                'delivered' => 'Entregue',
                'failed' => 'Falhou',
                'canceled' => 'Cancelada',
            ],
            'delivery_zone_status' => [
                'ativo' => 'Ativa',
                'inativo' => 'Inativa',
            ],
            'company_status' => [
                'ativa' => 'Ativa',
                'active' => 'Ativa',
                'teste' => 'Em teste',
                'trial' => 'Em teste',
                'suspensa' => 'Suspensa',
                'suspended' => 'Suspensa',
                'cancelada' => 'Cancelada',
                'canceled' => 'Cancelada',
            ],
            'company_subscription_status' => [
                'ativa' => 'Ativa',
                'trial' => 'Em teste',
                'inadimplente' => 'Inadimplente',
                'suspensa' => 'Suspensa',
                'cancelada' => 'Cancelada',
                'vencida' => 'Vencida',
                'expired' => 'Vencida',
            ],
            'plan_status' => [
                'ativo' => 'Ativo',
                'inativo' => 'Inativo',
                'active' => 'Ativo',
                'inactive' => 'Inativo',
            ],
            'subscription_status' => [
                'ativa' => 'Ativa',
                'trial' => 'Em teste',
                'vencida' => 'Vencida',
                'cancelada' => 'Cancelada',
            ],
            'billing_cycle' => [
                'mensal' => 'Mensal',
                'anual' => 'Anual',
                'monthly' => 'Mensal',
                'yearly' => 'Anual',
            ],
            'subscription_payment_status' => [
                'pendente' => 'Pendente',
                'pago' => 'Pago',
                'vencido' => 'Vencido',
                'cancelado' => 'Cancelado',
            ],
        ];

        $map = $maps[$context] ?? null;
        if (is_array($map) && array_key_exists($raw, $map)) {
            return $map[$raw];
        }

        $normalized = str_replace(['_', '-'], ' ', $raw);
        return ucfirst($normalized);
    }
}

if (!function_exists('status_badge_class')) {
    function status_badge_class(string $context, mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));

        $maps = [
            'order_status' => [
                'pending' => 'status-pending',
                'received' => 'status-received',
                'preparing' => 'status-preparing',
                'ready' => 'status-ready',
                'delivered' => 'status-delivered',
                'paid' => 'status-paid',
                'finished' => 'status-finished',
                'canceled' => 'status-canceled',
            ],
            'order_payment_status' => [
                'pending' => 'status-pending',
                'partial' => 'status-partial',
                'paid' => 'status-paid',
                'canceled' => 'status-canceled',
            ],
            'order_channel' => [
                'table' => 'status-received',
                'delivery' => 'status-ready',
                'pickup' => 'status-partial',
                'counter' => 'status-default',
            ],
            'order_operational_flag' => [
                'paid_waiting_production' => 'status-paid-waiting-production',
            ],
            'payment_status' => [
                'pending' => 'status-pending',
                'paid' => 'status-paid',
                'failed' => 'status-failed',
                'refunded' => 'status-refunded',
                'canceled' => 'status-canceled',
            ],
            'print_log_status' => [
                'success' => 'status-success',
                'failed' => 'status-failed',
            ],
            'cash_register_status' => [
                'open' => 'status-open',
                'closed' => 'status-closed',
            ],
            'command_status' => [
                'aberta' => 'status-open',
                'fechada' => 'status-closed',
                'cancelada' => 'status-canceled',
            ],
            'table_status' => [
                'livre' => 'status-free',
                'ocupada' => 'status-busy',
                'aguardando_fechamento' => 'status-waiting',
                'bloqueada' => 'status-blocked',
            ],
            'delivery_status' => [
                'pending' => 'status-pending',
                'assigned' => 'status-received',
                'in_route' => 'status-preparing',
                'delivered' => 'status-delivered',
                'failed' => 'status-failed',
                'canceled' => 'status-canceled',
            ],
            'delivery_zone_status' => [
                'ativo' => 'status-active',
                'inativo' => 'status-inactive',
            ],
            'subscription_status' => [
                'ativa' => 'status-active',
                'trial' => 'status-trial',
                'vencida' => 'status-overdue',
                'cancelada' => 'status-canceled',
            ],
            'subscription_payment_status' => [
                'pendente' => 'status-pending',
                'pago' => 'status-paid',
                'vencido' => 'status-overdue',
                'cancelado' => 'status-canceled',
            ],
            'company_status' => [
                'ativa' => 'status-active',
                'active' => 'status-active',
                'teste' => 'status-trial',
                'trial' => 'status-trial',
                'suspensa' => 'status-suspended',
                'suspended' => 'status-suspended',
                'cancelada' => 'status-canceled',
                'canceled' => 'status-canceled',
            ],
            'company_subscription_status' => [
                'ativa' => 'status-active',
                'trial' => 'status-trial',
                'inadimplente' => 'status-overdue',
                'suspensa' => 'status-suspended',
                'cancelada' => 'status-canceled',
                'vencida' => 'status-overdue',
            ],
            'plan_status' => [
                'ativo' => 'status-active',
                'inativo' => 'status-inactive',
                'active' => 'status-active',
                'inactive' => 'status-inactive',
            ],
        ];

        $map = $maps[$context] ?? [];
        return $map[$raw] ?? 'status-default';
    }
}
