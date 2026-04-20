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
            'Content-Security-Policy' => "base-uri 'self'; form-action 'self'; frame-ancestors 'self'",
        ];

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->content;
    }
}
