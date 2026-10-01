<?php

namespace App\Actions\Platform;

use App\Enums\CompanyStatus;
use App\Exceptions\Provisioning\CompanyNotProvisionableException;
use App\Exceptions\Provisioning\WorkspaceProvisioningException;
use App\Models\Company;
use App\Models\PlatformUser;
use App\Services\Platform\WorkspaceDatabaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provision the physical Workspace database for a Company: create it, migrate it, verify it,
 * and move the Company to `active`. On failure, move the Company to `provisioning_failed`.
 *
 * Single business/application operation (Action-first). Web/CLI/Job entry points converge here.
 *
 * Confirmed decisions:
 *  - OD-1: a run may only start by atomically claiming pending/provisioning_failed →
 *    provisioning; a Company already `provisioning` is refused (no concurrent run). On (re)start
 *    the Workspace database is dropped-if-present and recreated fresh — a partial DB is never
 *    reused. Failure/timeout → provisioning_failed; never `active` on a failed/partial run.
 *  - OD-2: an `active` Company is rejected (not a silent success, no reprovision, no force).
 */
class ProvisionWorkspaceAction
{
    public function __construct(
        private readonly WorkspaceDatabaseService $workspaceDatabase,
    ) {}

    /**
     * @throws CompanyNotProvisionableException on an invalid status / concurrent run
     * @throws WorkspaceProvisioningException on a provisioning failure (after marking failed)
     */
    public function execute(Company $company, ?PlatformUser $actor = null): Company
    {
        $this->assertProvisionable($company);
        $this->claimForProvisioning($company, $actor);

        Log::info('Workspace provisioning started', [
            'company_id' => $company->id,
            'database_name' => $company->database_name,
        ]);

        try {
            $this->workspaceDatabase->recreateDatabase($company);
            $this->workspaceDatabase->runWorkspaceMigrations($company);
            $this->verifyWorkspace($company);
            $this->markActive($company, $actor);
        } catch (Throwable $exception) {
            $this->markProvisioningFailed($company, $actor);

            Log::error('Workspace provisioning failed', [
                'company_id' => $company->id,
                'database_name' => $company->database_name,
                'error' => $exception->getMessage(),
            ]);

            throw new WorkspaceProvisioningException(
                "Provisioning failed for company [{$company->id}]: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        Log::info('Workspace provisioning completed', [
            'company_id' => $company->id,
            'database_name' => $company->database_name,
        ]);

        return $company->refresh();
    }

    /**
     * Reject/refuse statuses that must never provision. Leaves state unchanged.
     */
    private function assertProvisionable(Company $company): void
    {
        $status = $company->status;

        if ($status === CompanyStatus::Provisioning) {
            throw CompanyNotProvisionableException::concurrentRun($company);
        }

        if (! in_array($status, [CompanyStatus::Pending, CompanyStatus::ProvisioningFailed], true)) {
            // active / suspended / deactivated all reject here (OD-2 for active).
            throw CompanyNotProvisionableException::forStatus($company, $status);
        }
    }

    /**
     * Atomically claim the Company into `provisioning`. The conditional UPDATE guarantees only
     * one run can win under concurrency: if another run already claimed it (or the status is no
     * longer provisionable), zero rows are affected and we refuse.
     */
    private function claimForProvisioning(Company $company, ?PlatformUser $actor): void
    {
        $affected = DB::connection('platform')
            ->table('companies')
            ->where('id', $company->id)
            ->whereIn('status', [
                CompanyStatus::Pending->value,
                CompanyStatus::ProvisioningFailed->value,
            ])
            ->update([
                'status' => CompanyStatus::Provisioning->value,
                'updated_by' => $actor?->id ?? $company->updated_by,
                'updated_at' => Carbon::now(),
            ]);

        if ($affected === 0) {
            throw CompanyNotProvisionableException::concurrentRun($company);
        }

        $company->refresh();
    }

    /**
     * Verify the Workspace is usable inside tenant context: the tenant connection reaches the
     * Company's own database and there are no pending Workspace migrations. Never substitutes a
     * Platform read.
     */
    private function verifyWorkspace(Company $company): void
    {
        if (! $this->workspaceDatabase->databaseExists($company)) {
            throw new WorkspaceProvisioningException(
                "Verification failed: workspace database [{$company->database_name}] does not exist."
            );
        }

        $company->run(function () use ($company): void {
            $activeDatabase = DB::connection()->getDatabaseName();

            if ($activeDatabase !== $company->database_name) {
                throw new WorkspaceProvisioningException(
                    "Verification failed: tenant connection reached [{$activeDatabase}], expected [{$company->database_name}]."
                );
            }
        });

        if ($this->workspaceDatabase->pendingMigrationCount($company) !== 0) {
            throw new WorkspaceProvisioningException(
                "Verification failed: workspace [{$company->database_name}] has pending migrations."
            );
        }
    }

    private function markActive(Company $company, ?PlatformUser $actor): void
    {
        $this->transitionStatus($company, CompanyStatus::Active, $actor);
    }

    private function markProvisioningFailed(Company $company, ?PlatformUser $actor): void
    {
        $this->transitionStatus($company, CompanyStatus::ProvisioningFailed, $actor);
    }

    /**
     * Small explicit status transition on the platform connection.
     */
    private function transitionStatus(Company $company, CompanyStatus $status, ?PlatformUser $actor): void
    {
        DB::connection('platform')->transaction(function () use ($company, $status, $actor): void {
            DB::connection('platform')
                ->table('companies')
                ->where('id', $company->id)
                ->update([
                    'status' => $status->value,
                    'updated_by' => $actor?->id ?? $company->updated_by,
                    'updated_at' => Carbon::now(),
                ]);
        });

        $company->refresh();
    }
}
