<?php

namespace App\Support\Platform;

use InvalidArgumentException;

/**
 * A normalized, validated hostname value.
 *
 * Normalization: trim → lowercase → strip a single trailing dot.
 * Validation: rejects protocol, path, port, and wildcard prefixes; enforces hostname syntax
 * and length limits (total ≤ 253, each label 1–63, letters/digits/hyphen, no leading/trailing
 * hyphen per label).
 *
 * This is a cohesive domain concept (a hostname), not a generic helper.
 */
final class Hostname
{
    private function __construct(
        public readonly string $value,
    ) {}

    /**
     * Normalize and validate a raw hostname string.
     *
     * @throws InvalidArgumentException when the value is not a valid hostname
     */
    public static function fromString(string $raw): self
    {
        $normalized = self::normalize($raw);

        self::assertValid($normalized);

        return new self($normalized);
    }

    /**
     * Normalize only (no validation). Used where a caller wants the canonical form for a lookup.
     */
    public static function normalize(string $raw): string
    {
        $value = strtolower(trim($raw));

        // Remove a single trailing dot (FQDN root), if present.
        if (str_ends_with($value, '.')) {
            $value = substr($value, 0, -1);
        }

        return $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function assertValid(string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException('Hostname must not be empty.');
        }

        if (str_contains($value, '://')) {
            throw new InvalidArgumentException('Hostname must not contain a protocol.');
        }

        if (str_contains($value, '/')) {
            throw new InvalidArgumentException('Hostname must not contain a path.');
        }

        // Reject a port (":" followed by digits, e.g. host:8080).
        if (preg_match('/:\d+$/', $value) === 1 || str_contains($value, ':')) {
            throw new InvalidArgumentException('Hostname must not contain a port.');
        }

        if (str_starts_with($value, '*.') || str_contains($value, '*')) {
            throw new InvalidArgumentException('Hostname must not contain a wildcard.');
        }

        if (strlen($value) > 253) {
            throw new InvalidArgumentException('Hostname must not exceed 253 characters.');
        }

        $labels = explode('.', $value);

        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63) {
                throw new InvalidArgumentException('Each hostname label must be 1–63 characters.');
            }

            if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label) !== 1) {
                throw new InvalidArgumentException("Invalid hostname label [{$label}].");
            }
        }
    }
}
