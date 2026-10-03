<?php

namespace App\Console\Commands;

use App\Actions\Workspace\MigrateWorkspaceAction;
use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Run pending tenant migrations against existing active workspaces (1F-G).
 *
 * Thin: resolves the target Company/Companies on the platform connection and delegates to
 * the single MigrateWorkspaceAction. No drop/recreate, idempotent. One company failing is
 * reported and does not abort the others.
 */
class MigrateWorkspaceCommand extends Command
{
    protected $signature = 'workspace:migrate
        {company? : Company ULID or slug (omit with --all)}
        {--all : Migrate every active Company workspace}';

    protected $description = 'Run pending Workspace (tenant) migrations against existing active workspaces';

    public function handle(MigrateWorkspaceAction $migrateWorkspace): int
    {
        $companies = $this->resolveTargets();

        if ($companies === null) {
            return self::FAILURE;
        }

        if ($companies->isEmpty()) {
            $this->info('No matching active companies to migrate.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($companies as $company) {
            try {
                $migrateWorkspace->execute($company);
                $this->info("Migrated workspace for company [{$company->id}] ({$company->slug}).");
            } catch (Throwable $exception) {
                $failures++;
                $this->error("Failed to migrate company [{$company->id}] ({$company->slug}): {$exception->getMessage()}");
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
