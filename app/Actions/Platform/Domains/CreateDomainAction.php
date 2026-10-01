<?php

namespace App\Actions\Platform\Domains;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Domain;
use App\Models\PlatformUser;
use App\Support\Platform\Hostname;
use App\Support\Platform\ReservedLabels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create a Platform Domain for a Company.
 *
 * Normalizes + validates the hostname, enforces global uniqueness (soft-deleted identity is
 * reserved; reuse restores the existing record), sets audit + initial status, and — when
 * requested — makes the new Domain primary atomically.
 *
 * Custom domains are created `pending` and are never auto-activated here. Subdomains are built
 * from the configurable base domain and reject reserved labels; they are also created `pending`
 * (activation is a separate trusted operation).
 */
class CreateDomainAction
{
    public function execute(CreateDomainData $data, PlatformUser $actor): Domain
    {
        $hostname = $this->resolveHostname($data);

        return DB::connection('platform')->transaction(function () use ($data, $hostname, $actor) {
            $existing = Domain::on('platform')
                ->withTrashed()
                ->where('domain', $hostname)
                ->first();

            if ($existing !== null && ! $existing->trashed()) {
                throw ValidationException::withMessages([
                    'domain' => ["The domain [{$hostname}] is already in use."],
                ]);
            }

            $domain = $existing ?? new Domain;
            $domain->setConnection('platform');

            if ($existing !== null) {
                // Reuse the reserved identity by restoring, then re-assign to this company.
                $domain->restore();
            }

            $domain->id = $domain->id ?? (string) Str::ulid();
            $domain->forceFill([
                'company_id' => $data->companyId,
                'domain' => $hostname,
                'type' => $data->type,
                'status' => DomainStatus::Pending,
                'verified_at' => null,
                'is_primary' => false,
                'created_by' => $existing->created_by ?? $actor->id,
                'updated_by' => $existing !== null ? $actor->id : null,
            ]);
            $domain->save();

            if ($data->isPrimary) {
                $this->makePrimary($domain, $actor);
            }

            return $domain->refresh();
        });
    }

    /**
     * Produce the final hostname: for a subdomain, combine the label with the configurable base
     * domain and reject reserved labels; for a custom domain, validate the hostname directly.
     */
    private function resolveHostname(CreateDomainData $data): string
    {
        if ($data->type === DomainType::Subdomain) {
            return $this->buildSubdomain($data->domain);
        }

        return (string) Hostname::fromString($data->domain);
    }

    private function buildSubdomain(string $label): string
    {
        $label = strtolower(trim($label));

        if (ReservedLabels::isReserved($label)) {
            throw ValidationException::withMessages([
                'domain' => ["The subdomain label [{$label}] is reserved."],
            ]);
        }

        $baseDomain = config('tenancy.base_domain');

        if (! is_string($baseDomain) || trim($baseDomain) === '') {
            throw ValidationException::withMessages([
                'domain' => ['The platform base domain is not configured.'],
            ]);
        }

        // Validate the label on its own, then validate the full hostname.
        $host = Hostname::fromString($label.'.'.$baseDomain);

        return (string) $host;
    }

    /**
     * Atomically make $domain the primary for its company (demote others, promote this one).
     */
    private function makePrimary(Domain $domain, PlatformUser $actor): void
    {
        Domain::on('platform')
            ->where('company_id', $domain->company_id)
            ->where('id', '!=', $domain->id)
            ->where('is_primary', true)
            ->update([
                'is_primary' => false,
                'updated_by' => $actor->id,
                'updated_at' => Carbon::now(),
            ]);

        $domain->forceFill(['is_primary' => true, 'updated_by' => $actor->id])->save();
    }
}
