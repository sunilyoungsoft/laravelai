<?php

namespace App\Support\Platform;

/**
 * Single source of truth for reserved public/platform labels.
 *
 * These labels may not be used as a Company slug or as a platform-managed subdomain label.
 * Previously this list lived only in CompanyService; it is centralized here so the same
 * business rule is not duplicated across slug validation and subdomain validation.
 */
final class ReservedLabels
{
    /**
     * @var list<string>
     */
    private const LABELS = [
        'www',
        'api',
        'admin',
        'platform',
        'app',
        'mail',
        'ftp',
        'localhost',
        'staging',
        'static',
        'assets',
        'cdn',
        'status',
        'health',
        'auth',
        'login',
        'billing',
        'support',
        'docs',
        'dashboard',
        'system',
        'root',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::LABELS;
    }

    public static function isReserved(string $label): bool
    {
        return in_array(strtolower(trim($label)), self::LABELS, true);
    }
}
