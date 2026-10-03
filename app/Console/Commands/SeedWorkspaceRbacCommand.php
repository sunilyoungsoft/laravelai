<?php

namespace App\Console\Commands;

use App\Actions\Workspace\SeedWorkspaceRbacAction;
use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Services\Platform\WorkspaceDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Seed baseline Workspace RBAC (the workspace-admin system role + proof permissions) into
 * existing active workspaces (1G).
 *
 * This is the existing-workspace counterpart to the seed that runs automatically during
 * provisioning. It is idempotent — safe to rerun; it never drops data. `workspace:migrate`
 * stays purely structural (schema only); this command handles seeding separately.
 *
 * Thin: resolves the target Company/Companies on the platform connection and delegates to the
 * single SeedWorkspaceRbacAction, run inside each tenant's context. One company failing is
 * reported and does not abort the others.
 */
class SeedWorkspaceRbacCommand extends Command
{
    protected $signature = 'workspace:seed-rbac
        {company? : Company ULID or slug (omit with --all)}
        {--all : Seed every active Company workspace}';

    protected $description = 'Seed baseline Workspace RBAC (workspace-admin role + proof permissions) into existing workspaces';

    public function handle(SeedWorkspaceRbacAction $seedWorkspaceRbac, WorkspaceDatabaseService $workspaceDatabase): int
    {
        $companies = $this->resolveTargets();

        if ($companies === null) {
            return self::FAILURE;
        }

        if ($companies->isEmpty()) {
            $this->info('No matching active companies to seed.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($companies as $company) {
            try {
                if (! $workspaceDatabase->databaseExists($company)) {
                    $this->warn("Skipping company [{$company->id}] ({$company->slug}): no workspace database.");

                    continue;
                }

                $company->run(fn () => $seedWorkspaceRbac->execute());
                $this->info("Seeded Workspace RBAC for company [{$company->id}] ({$company->slug}).");
            } catch (Throwable $exception) {
                $failures++;
                $this->error("Failed to seed company [{$company->id}] ({$company->slug}): {$exception->getMessage()}");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, Company>|null null when the arguments are invalid
     */
    private function resolveTargets(): ?Collection
    {
        $all = (bool) $this->option('all');
        $identifier = $this->argument('company');

        if ($all && $identifier !== null) {
            $this->error('Provide either a company or --all, not both.');

            return null;
        }

        if (! $all && $identifier === null) {
            $this->error('Specify a company (ULID or slug) or pass --all.');

            return null;
        }

        if ($all) {
            return Company::on('platform')
                ->where('status', CompanyStatus::Active->value)
                ->get();
        }

        $company = Company::on('platform')
            ->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();

        if ($company === null) {
            $this->error("Company not found for [{$identifier}].");

            return null;
        }

        return collect([$company]);
    }
}
