<?php

namespace App\Actions\Platform;

use App\Enums\CompanyStatus;
use App\Enums\DomainStatus;
use App\Exceptions\Tenancy\UnresolvableDomainException;
use App\Models\Company;
use App\Models\Domain;
use App\Support\Platform\Hostname;

/**
 * Resolve an incoming hostname to the owning Company using Platform data only.
 *
 * This Action ONLY resolves the Company; it never initializes tenancy (that is the HTTP
 * middleware's responsibility). The lookup runs on the `platform` connection against the
 * application-owned `domains` table — never a Workspace database, never Stancl's stock domains
 * table, and never a client-supplied company/tenant/database identifier.
 *
 * A hostname resolves only when its Domain is `active` and the owning Company is `active`.
 * Anything else fails safely with UnresolvableDomainException.
 */
class ResolveCompanyByHostnameAction
{
    public function execute(string $hostname): Company
    {
        $normalized = Hostname::normalize($hostname);

        $domain = Domain::on('platform')
            ->where('domain', $normalized)
            ->where('status', DomainStatus::Active->value)
            ->first();

        if ($domain === null) {
            throw UnresolvableDomainException::forHostname($normalized);
        }

        $company = Company::on('platform')
            ->whereKey($domain->company_id)
            ->where('status', CompanyStatus::Active->value)
            ->first();

        if ($company === null) {
            throw UnresolvableDomainException::forHostname($normalized);
        }

        return $company;
    }
}
