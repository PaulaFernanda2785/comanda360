<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const AUTH_USER_KEY = 'auth_user';
    private const LAST_ACTIVITY_KEY = '_auth_last_activity_at';

    private static bool $userResolved = false;
    private static ?array $resolvedUser = null;
    private static bool $timedOut = false;

    public static function user(): ?array
    {
        if (self::$userResolved) {
            return self::$resolvedUser;
        }

        Session::start();
        $user = Session::get(self::AUTH_USER_KEY);

        if (!is_array($user) || $user === []) {
            self::$userResolved = true;
            self::$resolvedUser = null;

            return null;
        }

        $now = time();
        $lastActivityAt = (int) Session::get(self::LAST_ACTIVITY_KEY, 0);
        $timeoutSeconds = session_idle_timeout_seconds();

        if ($lastActivityAt > 0 && ($now - $lastActivityAt) >= $timeoutSeconds) {
            self::logoutInternal(true);
            self::$userResolved = true;
            self::$resolvedUser = null;

            return null;
        }

        Session::put(self::LAST_ACTIVITY_KEY, $now);
        self::$userResolved = true;
        self::$resolvedUser = $user;

        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $user): void
    {
        Session::start();
        session_regenerate_id(true);
        Session::put(self::AUTH_USER_KEY, $user);
        Session::put(self::LAST_ACTIVITY_KEY, time());
        self::$userResolved = true;
        self::$resolvedUser = $user;
        self::$timedOut = false;
    }

    public static function updateUser(array $user): void
    {
        Session::start();
        Session::put(self::AUTH_USER_KEY, $user);
        self::$userResolved = true;
        self::$resolvedUser = $user;
    }

    public static function logout(): void
    {
        self::logoutInternal(false);
    }

    public static function consumeTimedOut(): bool
    {
        $timedOut = self::$timedOut;
        self::$timedOut = false;

        return $timedOut;
    }

    private static function logoutInternal(bool $timedOut): void
    {
        Session::invalidate();
        self::$userResolved = true;
        self::$resolvedUser = null;
        self::$timedOut = $timedOut;
    }
}
