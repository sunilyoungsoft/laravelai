<?php

namespace App\Exceptions\Tenancy;

use RuntimeException;

/**
 * Thrown when an incoming hostname cannot be resolved to an active Company.
 *
 * The caller (HTTP middleware) must fail safely: no tenancy is initialized, no Workspace is
 * accessed, and there is no fallback to the Platform database for tenant business data.
 */
class UnresolvableDomainException extends RuntimeException
{
    public static function forHostname(string $hostname): self
    {
        return new self("No active company resolves for hostname [{$hostname}].");
    }
}
