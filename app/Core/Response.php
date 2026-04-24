<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private readonly string $content = '',
        private readonly int $status = 200,
        private readonly array $headers = []
    ) {}

    public static function make(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        $location = self::normalizeRedirectLocation($location);
        if ($status < 300 || $status > 399) {
            $status = 302;
        }

        if ($location !== '' && str_starts_with($location, '/') && preg_match('#^(https?:)?//#i', $location) !== 1) {
            $location = \base_url($location);
        }

        return new self('', $status, ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        $headers = $this->headers + [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'",
        ];

        foreach ($headers as $name => $value) {
            $safeName = self::sanitizeHeaderName((string) $name);
            $safeValue = self::sanitizeHeaderValue((string) $value);
            if ($safeName === '' || $safeValue === '') {
                continue;
            }

            header($safeName . ': ' . $safeValue);
        }
        echo $this->content;
    }

    private static function normalizeRedirectLocation(string $location): string
    {
        $location = trim(self::sanitizeHeaderValue($location));
        if ($location === '') {
            return \base_url('/');
        }

        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        if (str_starts_with($location, '//') || preg_match('#^[a-z][a-z0-9+\-.]*:#i', $location) === 1) {
            return \base_url('/');
        }

        return str_starts_with($location, '/') ? $location : '/' . ltrim($location, '/');
    }

    private static function sanitizeHeaderName(string $name): string
    {
        $name = trim($name);
        return preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name) === 1 ? $name : '';
    }

    private static function sanitizeHeaderValue(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
