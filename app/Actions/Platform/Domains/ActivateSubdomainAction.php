<?php

namespace App\Actions\Platform\Domains;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Models\PlatformUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Trusted activation of a platform-managed subdomain.
 *
 * Only `subdomain` domains may be activated this way (the platform controls the hostname).
 * Custom domains must go through future verification and are rejected here — Phase 1D does not
 * auto-activate custom domains and adds no DNS/SSL automation.
 */
class ActivateSubdomainAction
{
    public function execute(Domain $domain, PlatformUser $actor): Domain
    {
        if ($domain->type !== DomainType::Subdomain) {
            throw new InvalidArgumentException(
                'Only platform-managed subdomains can be activated by this action.'
            );
        }

        return DB::connection('platform')->transaction(function () use ($domain, $actor) {
            $domain->forceFill([
                'status' => DomainStatus::Active,
                'verified_at' => Carbon::now(),
                'updated_by' => $actor->id,
            ])->save();

            return $domain->refresh();
        });
    }
}
