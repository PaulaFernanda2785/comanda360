<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth\LoginService;
use RuntimeException;

final class LoginController extends Controller
{
    public function show(Request $request): Response
    {
        if (Auth::check()) {
            $user = Auth::user() ?? [];
            return $this->redirect($this->resolveLandingRoute($user));
        }

        return $this->view('auth/login', [
            'title' => 'Login',
            'error' => null,
        ], 'layouts/auth');
    }

    public function store(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        try {
            if ($email === '' || $password === '') {
                throw new RuntimeException('Informe e-mail e senha.');
            }

            $service = new LoginService();
            $user = $service->attempt($email, $password);

            Auth::login($user);

            return $this->redirect($this->resolveLandingRoute($user));
        } catch (RuntimeException $e) {
            return $this->view('auth/login', [
                'title' => 'Login',
                'error' => $e->getMessage(),
            ], 'layouts/auth');
        }
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        return $this->redirect('/login');
    }

    private function resolveLandingRoute(array $user): string
    {
        if ((int) ($user['is_saas_user'] ?? 0) === 1 || (string) ($user['role_context'] ?? '') === 'saas') {
            return '/saas/dashboard';
        }

        return match ((string) ($user['role_slug'] ?? '')) {
            'kitchen' => '/admin/kitchen',
            'waiter', 'delivery' => '/admin/orders',
            default => '/admin/dashboard',
        };
    }
}
