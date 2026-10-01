<?php

namespace App\Actions\Platform\Domains;

use App\Models\Domain;
use App\Models\PlatformUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Make a Domain the single primary for its Company, atomically.
 *
 * Demotes the current primary and promotes the target in one platform transaction with a row
 * lock over the Company's domains, so concurrent requests cannot both win. The single-primary
 * invariant is enforced server-side only; UI is never trusted.
 */
class SetPrimaryDomainAction
{
    public function execute(Domain $domain, PlatformUser $actor): Domain
    {
        return DB::connection('platform')->transaction(function () use ($domain, $actor) {
            // Lock this company's domain rows for the duration of the swap.
            Domain::on('platform')
                ->where('company_id', $domain->company_id)
                ->lockForUpdate()
                ->get();

            Domain::on('platform')
                ->where('company_id', $domain->company_id)
                ->where('id', '!=', $domain->id)
                ->where('is_primary', true)
                ->update([
                    'is_primary' => false,
                    'updated_by' => $actor->id,
                    'updated_at' => Carbon::now(),
                ]);

            $domain->forceFill([
                'is_primary' => true,
                'updated_by' => $actor->id,
            ])->save();

            return $domain->refresh();
        });
    }
}
