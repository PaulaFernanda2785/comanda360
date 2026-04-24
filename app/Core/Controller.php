<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Shared\AppShellService;
use Throwable;

abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'layouts/app'): Response
    {
        $shouldResolveShellData = in_array($layout, ['layouts/app', 'layouts/saas'], true);
        if ($shouldResolveShellData && (!array_key_exists('appShellTheme', $data) || !array_key_exists('navItems', $data))) {
            try {
                $user = is_array($data['user'] ?? null) ? $data['user'] : Auth::user();
                $shellService = new AppShellService();

                if ($layout === 'layouts/app' && !array_key_exists('appShellTheme', $data)) {
                    $data['appShellTheme'] = $shellService->resolveForUser($user);
                }

                if (!array_key_exists('navItems', $data)) {
                    $contextHint = $layout === 'layouts/saas' ? 'saas' : 'company';
                    $data['navItems'] = $shellService->resolveNavigationForUser($user, $contextHint);
                }
            } catch (Throwable) {
                if ($layout === 'layouts/app' && !array_key_exists('appShellTheme', $data)) {
                    $data['appShellTheme'] = [];
                }
                if (!array_key_exists('navItems', $data)) {
                    $data['navItems'] = [];
                }
            }
        }

        $data['flashSuccess'] = Session::getFlash('success');
        $data['flashError'] = Session::getFlash('error');

        return Response::make(View::render($template, $data, $layout));
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function backWithError(string $message, string $to): Response
    {
        Session::flash('error', $message);
        return $this->redirect($to);
    }

    protected function backWithSuccess(string $message, string $to): Response
    {
        Session::flash('success', $message);
        return $this->redirect($to);
    }

    protected function guardSingleSubmit(Request $request, string $scope, string $redirectTo): ?Response
    {
        $result = validate_form_submission($request->all(), $scope, 5);
        if (($result['ok'] ?? false) === true) {
            return null;
        }

        return $this->backWithError((string) ($result['message'] ?? 'Nao foi possivel validar a requisicao.'), $redirectTo);
    }

    protected function guardPublicRateLimit(
        Request $request,
        string $scope,
        int $maxAttempts,
        int $windowSeconds,
        string $redirectTo
    ): ?Response {
        $clientIp = trim((string) ($request->server['REMOTE_ADDR'] ?? 'unknown'));
        $result = public_rate_limit_check($scope, $clientIp, $maxAttempts, $windowSeconds);
        if (($result['ok'] ?? false) === true) {
            return null;
        }

        $retryAfter = max(1, (int) ($result['retry_after'] ?? $windowSeconds));
        $minutes = max(1, (int) ceil($retryAfter / 60));
        return $this->backWithError(
            'Muitas tentativas em pouco tempo. Aguarde ' . $minutes . ' minuto(s) antes de tentar novamente.',
            $redirectTo
        );
    }
}
