<?php
declare(strict_types=1);

return [
    'app_name' => getenv('APP_NAME') ?: 'Comanda360',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'base_url' => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Belem',
    'session_name' => getenv('SESSION_NAME') ?: 'comanda360_session',
    'session_secure' => filter_var(getenv('SESSION_SECURE') ?: '', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
    'session_same_site' => getenv('SESSION_SAMESITE') ?: 'Lax',
    'session_idle_timeout' => (int) (getenv('SESSION_IDLE_TIMEOUT') ?: 1800),
];
