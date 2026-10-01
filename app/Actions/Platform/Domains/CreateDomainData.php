<?php

namespace App\Actions\Platform\Domains;

use App\Enums\DomainType;

/**
 * Typed, readonly application input for creating a Domain.
 *
 * Carries application input only. It does not own HTTP validation, authorization, persistence,
 * or tenancy switching. Normalization of the hostname happens in the Action via the Hostname
 * value object.
 */
final readonly class CreateDomainData
{
    public function __construct(
        public string $companyId,
        public string $domain,
        public DomainType $type,
        public bool $isPrimary = false,
    ) {}
}
