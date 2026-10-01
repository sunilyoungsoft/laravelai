<?php

namespace App\Jobs\Platform;

use App\Actions\Platform\ProvisionWorkspaceAction;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Asynchronous entry point for Workspace provisioning.
 *
 * Carries only the Company ULID (never the HTTP request context) and re-resolves the Company
 * on the platform connection at run time, then delegates to the single ProvisionWorkspaceAction.
 * Contains no provisioning logic of its own.
 *
 * The job does not assume it starts in tenant context; the Action enters tenant context via
 * Stancl only where needed (migrations/verification). A failed run leaves the Company in
 * `provisioning_failed` (handled inside the Action) and the exception propagates so the queue
 * records the failure.
 */
class ProvisionWorkspaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $companyId,
    ) {}

    public function handle(ProvisionWorkspaceAction $provisionWorkspace): void
    {
        $company = Company::on('platform')->whereKey($this->companyId)->first();

        if ($company === null) {
            throw new RuntimeException("Company [{$this->companyId}] not found for provisioning.");
        }

        $provisionWorkspace->execute($company);
    }
}
