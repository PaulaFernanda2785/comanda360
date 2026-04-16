<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function user(): ?array
    {
        Session::start();
        return Session::get('auth_user');
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $user): void
    {
        Session::start();
        session_regenerate_id(true);
        Session::put('auth_user', $user);
    }

    public static function logout(): void
    {
        Session::start();
        Session::destroy();
    }
}
