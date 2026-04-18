<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware
{
    public function handle(Request $request): ?Response
    {
        if (!Auth::check()) {
            $message = Auth::consumeTimedOut()
                ? 'Sessao encerrada com seguranca apos 30 minutos de inatividade. Entre novamente.'
                : 'Faca login para acessar esta area.';

            Session::flash('error', $message);
            return Response::redirect('/login');
        }

        return null;
    }
}
