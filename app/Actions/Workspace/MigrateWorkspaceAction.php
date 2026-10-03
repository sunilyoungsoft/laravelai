<?php

namespace App\Actions\Workspace;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Services\Platform\WorkspaceDatabaseService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Run pending tenant (workspace) migrations against an existing, already-provisioned
 * Company — without dropping or recreating the database (1F-G).
 *
 * This fills the gap left by ProvisionWorkspaceAction, which only migrates during initial
 * provisioning and uses drop+recreate on retry. When a new tenant migration is added (e.g.
 * workspace_users), active companies need it applied in place, preserving their data.
 *
 * Idempotent: Laravel's migrator only runs migrations not already recorded in the tenant's
 * own `migrations` ledger, so re-running is a no-op when nothing is pending. Migrations run
 * inside Stancl tenant context via WorkspaceDatabaseService, so they target the tenant
 * connection and never the platform database.
 */
class MigrateWorkspaceAction
{
    public function __construct(
        private readonly WorkspaceDatabaseService $workspaceDatabase,
    ) {}

    public function execute(Company $company): void
    {
        $this->assertMigratable($company);

        Log::info('Workspace migrate started', [
            'company_id' => $company->id,
            'database_name' => $company->database_name,
        ]);

        $this->workspaceDatabase->runWorkspaceMigrations($company);

        Log::info('Workspace migrate completed', [
            'company_id' => $company->id,
            'database_name' => $company->database_name,
        ]);
    }

    /**
     * Only an active Company with an existing workspace database may be migrated in place.
     * A pending/failed company has no (valid) database yet — that is provisioning's job.
     */
    private function assertMigratable(Company $company): void
    {
        if ($company->status !== CompanyStatus::Active) {
            throw new RuntimeException(
                "Company [{$company->id}] is not active (status: {$company->status->value}); ".
                'migrate applies only to provisioned workspaces.'
            );
        }

        if (! $this->workspaceDatabase->databaseExists($company)) {
            throw new RuntimeException(
                "Workspace database [{$company->database_name}] does not exist for company [{$company->id}]."
            );
        }
    }
}
