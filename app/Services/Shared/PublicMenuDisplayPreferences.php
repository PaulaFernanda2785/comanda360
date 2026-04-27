<?php
declare(strict_types=1);

namespace App\Services\Shared;

final class PublicMenuDisplayPreferences
{
    public const SHOW_TOTALS = 'show_public_totals';
    public const SHOW_TICKETS = 'show_public_tickets';

    public static function defaults(): array
    {
        return [
            self::SHOW_TOTALS => true,
            self::SHOW_TICKETS => true,
        ];
    }

    public static function normalize(array $preferences): array
    {
        $defaults = self::defaults();

        return [
            self::SHOW_TOTALS => self::normalizeFlag($preferences[self::SHOW_TOTALS] ?? null, $defaults[self::SHOW_TOTALS]),
            self::SHOW_TICKETS => self::normalizeFlag($preferences[self::SHOW_TICKETS] ?? null, $defaults[self::SHOW_TICKETS]),
        ];
    }

    public static function normalizeFlag(mixed $value, bool $fallback = true): bool
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'off', 'no', 'nao', 'não'], true)) {
            return false;
        }

        return $fallback;
    }
}
