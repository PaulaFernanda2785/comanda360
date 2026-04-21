<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\LoginService;
use App\Services\Marketing\LandingPageService;
use App\Services\Shared\AppShellService;
use RuntimeException;

final class LoginController extends Controller
{
    public function __construct(
        private readonly LandingPageService $landingPage = new LandingPageService()
    ) {}

    public function landing(Request $request): Response
    {
        if (Auth::check()) {
            $user = Auth::user() ?? [];
            return $this->redirectAuthenticatedUser($user);
        }

        return $this->renderPublicLandingPage();
    }

    public function show(Request $request): Response
    {
        if (Auth::check()) {
            $user = Auth::user() ?? [];
            return $this->redirectAuthenticatedUser($user);
        }

        $timedOut = Auth::consumeTimedOut();

        return $this->renderLoginPage(
            $timedOut ? 'Sessao encerrada com seguranca apos 30 minutos de inatividade. Entre novamente.' : null
        );
    }

    public function store(Request $request): Response
    {
        $guard = validate_form_submission($request->all(), 'auth.login', 2);
        if (($guard['ok'] ?? false) !== true) {
            return $this->renderLoginPage((string) ($guard['message'] ?? 'Nao foi possivel validar o envio do login.'));
        }

        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $clientIp = trim((string) ($request->server['REMOTE_ADDR'] ?? ''));

        try {
            if ($email === '' || $password === '') {
                throw new RuntimeException('Informe e-mail e senha.');
            }

            $service = new LoginService();
            $user = $service->attempt($email, $password, $clientIp);

            Auth::login($user);

            return $this->redirectAuthenticatedUser($user);
        } catch (RuntimeException $e) {
            return $this->renderLoginPage($e->getMessage());
        }
    }

    public function logout(Request $request): Response
    {
        if ($request->method !== 'POST') {
            return $this->redirect('/login');
        }

        $guard = validate_form_submission($request->all(), 'auth.logout', 2);
        if (($guard['ok'] ?? false) !== true) {
            Auth::logout();
            Session::start();
            Session::flash('error', 'Sessao encerrada por seguranca. Entre novamente.');
            return $this->redirect('/login');
        }

        Auth::logout();
        Session::start();
        $logoutReason = strtolower(trim((string) $request->input('logout_reason', '')));
        $message = $logoutReason === 'idle_timeout'
            ? 'Sessao encerrada com seguranca apos 30 minutos de inatividade.'
            : 'Logout realizado com sucesso.';
        Session::flash('success', $message);

        return $this->redirect('/login');
    }

    private function redirectAuthenticatedUser(array $user): Response
    {
        $landing = $this->resolveLandingRoute($user);
        if ($landing === '/login') {
            Auth::logout();
            Session::start();
            Session::flash('error', 'Perfil sem rota inicial permitida. Entre novamente.');
            return $this->redirect('/login');
        }

        return $this->redirect($landing);
    }

    private function renderPublicLandingPage(): Response
    {
        $landingPage = $this->landingPage->build();

        return $this->view('auth/login', [
            'title' => 'Comanda360',
            'seo' => $landingPage['seo'] ?? [],
            'landingPage' => $landingPage,
        ], 'layouts/public');
    }

    private function renderLoginPage(?string $error = null): Response
    {
        return $this->view('auth/access', [
            'title' => 'Login | Comanda360',
            'seo' => [
                'title' => 'Login | Comanda360',
                'description' => 'Acesse a plataforma Comanda360 com seu e-mail e senha.',
                'canonical' => app_url('/login'),
                'robots' => 'noindex,nofollow',
            ],
            'error' => $error,
        ], 'layouts/public');
    }

    private function resolveLandingRoute(array $user): string
    {
        $roleSlug = strtolower(trim((string) ($user['role_slug'] ?? '')));
        $context = strtolower(trim((string) ($user['role_context'] ?? '')));
        $isSaas = (int) ($user['is_saas_user'] ?? 0) === 1 || $context === 'saas';
        $shell = new AppShellService();

        if ($isSaas) {
            $saasPreferredRoute = match ($roleSlug) {
                'saas_support' => '/saas/companies',
                'saas_financial' => '/saas/subscription-payments',
                default => '/saas/dashboard',
            };
            return $this->resolveAllowedRoute($saasPreferredRoute, $shell->resolveNavigationForUser($user, 'saas'));
        }

        $companyPreferredRoute = match ($roleSlug) {
            'kitchen' => '/admin/kitchen',
            'waiter' => '/admin/orders',
            'delivery' => '/admin/deliveries',
            'cashier' => '/admin/cash-registers',
            'admin_establishment', 'manager' => '/admin/dashboard',
            default => '',
        };

        $navItems = $shell->resolveNavigationForUser($user, 'company');
        return $this->resolveAllowedRoute($companyPreferredRoute, $navItems);
    }

    private function resolveAllowedRoute(string $preferredRoute, array $navItems): string
    {
        $availableRoutes = [];
        foreach ($navItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $href = trim((string) ($item['href'] ?? ''));
            if ($href !== '') {
                $availableRoutes[] = $href;
            }
        }

        if ($preferredRoute !== '' && in_array($preferredRoute, $availableRoutes, true)) {
            return $preferredRoute;
        }

        if ($availableRoutes !== []) {
            return $availableRoutes[0];
        }

        return '/login';
    }
}
