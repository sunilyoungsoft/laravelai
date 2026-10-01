<?php

namespace App\Services\Platform;

use App\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stancl\Tenancy\Contracts\TenantDatabaseManager;

/**
 * Infrastructure service for the physical Workspace database of a Company.
 *
 * Responsibilities (a genuine, cohesive infrastructure concern reused by the provisioning
 * Action, retry, and verification):
 *   - create / drop / existence-check the physical Workspace database
 *   - run Workspace (tenant) migrations against the tenant database
 *   - report pending Workspace migrations
 *
 * Rules:
 *   - Database creation/deletion/existence use the CONFIGURED Stancl tenant database manager
 *     (resolved via $company->database()->manager()); this service does not hand-write
 *     CREATE/DROP SQL and does not depend on a concrete manager class.
 *   - Server-level DDL runs on the explicitly named `platform` connection.
 *   - Tenant context (for migrations/verification) is entered only through Stancl
 *     ($company->run(...)); this service never switches connections manually.
 *   - dropDatabase is guarded so it can only ever target the Company's own workspace database.
 */
class WorkspaceDatabaseService
{
    /**
     * The central connection used for server-level DDL (CREATE/DROP/existence).
     */
    private const DDL_CONNECTION = 'platform';

    /**
     * Workspace database name pattern: workspace_ + 26-char lowercase ULID.
     */
    private const WORKSPACE_DATABASE_PATTERN = '/^workspace_[0-9a-z]{26}$/';

    /**
     * Does the Company's Workspace database physically exist?
     */
    public function databaseExists(Company $company): bool
    {
        $manager = $this->ddlManager($company);

        return $manager->databaseExists($company->database_name);
    }

    /**
     * Create a fresh physical Workspace database for the Company.
     *
     * Uses the configured Stancl database manager on the `platform` connection.
     */
    public function createDatabase(Company $company): void
    {
        $manager = $this->ddlManager($company);

        $manager->createDatabase($company);
    }

    /**
     * Delete the Company's Workspace database, guarded so it can only target
     * this Company's own workspace_{ulid} database — never anything else.
     */
    public function dropDatabase(Company $company): void
    {
        $this->assertDeletableWorkspaceName($company->database_name);

        $manager = $this->ddlManager($company);

        $manager->deleteDatabase($company);
    }

    /**
     * Ensure a fresh Workspace database: drop any existing one first (OD-1: never reuse a
     * partially provisioned Workspace), then create a new one.
     */
    public function recreateDatabase(Company $company): void
    {
        if ($this->databaseExists($company)) {
            $this->dropDatabase($company);
        }

        $this->createDatabase($company);
    }

    /**
     * Run all Workspace migrations against the Company's tenant database.
     *
     * Executed inside Stancl tenant context via $company->run(); migrations therefore target
     * the dynamic `tenant` connection and never the `platform` database.
     */
    public function runWorkspaceMigrations(Company $company): void
    {
        $company->run(function () {
            Artisan::call('migrate', [
                '--path' => $this->workspaceMigrationsPath(),
                '--realpath' => true,
                '--force' => true,
            ]);
        });
    }

    /**
     * Number of Workspace migrations not yet applied to the Company's tenant database.
     * Runs inside tenant context so it reads the tenant's own migration ledger.
     */
    public function pendingMigrationCount(Company $company): int
    {
        return $company->run(function (): int {
            $applied = $this->appliedTenantMigrations();
            $available = $this->availableWorkspaceMigrationNames();

            return count(array_diff($available, $applied));
        });
    }

    /**
     * Applied migration names from the tenant database's own `migrations` ledger.
     * Returns an empty list if the ledger table does not exist yet.
     *
     * @return list<string>
     */
    private function appliedTenantMigrations(): array
    {
        $connection = DB::connection();

        if (! $connection->getSchemaBuilder()->hasTable('migrations')) {
            return [];
        }

        return $connection->table('migrations')->pluck('migration')->all();
    }

    /**
     * Workspace migration file names (without .php), as Laravel records them.
     *
     * @return list<string>
     */
    private function availableWorkspaceMigrationNames(): array
    {
        $files = glob($this->workspaceMigrationsPath().DIRECTORY_SEPARATOR.'*.php') ?: [];

        return array_values(array_map(
            static fn (string $file): string => str_replace('.php', '', basename($file)),
            $files
        ));
    }

    private function workspaceMigrationsPath(): string
    {
        return database_path('migrations'.DIRECTORY_SEPARATOR.'tenant');
    }

    /**
     * Resolve the configured Stancl tenant database manager and point it at the central
     * `platform` connection for server-level DDL.
     */
    private function ddlManager(Company $company): TenantDatabaseManager
    {
        $manager = $company->database()->manager();
        $manager->setConnection(self::DDL_CONNECTION);

        return $manager;
    }

    /**
     * Guard: refuse to delete anything that is not a valid workspace_{ulid} name.
     */
    private function assertDeletableWorkspaceName(string $databaseName): void
    {
        if (preg_match(self::WORKSPACE_DATABASE_PATTERN, $databaseName) !== 1) {
            throw new RuntimeException(
                "Refusing to drop database [{$databaseName}]: not a workspace_{ulid} name."
            );
        }
    }
}
