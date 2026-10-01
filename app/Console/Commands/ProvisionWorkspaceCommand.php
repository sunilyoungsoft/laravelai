<?php

namespace App\Console\Commands;

use App\Actions\Platform\ProvisionWorkspaceAction;
use App\Exceptions\Provisioning\WorkspaceProvisioningException;
use App\Models\Company;
use Illuminate\Console\Command;
use Throwable;

/**
 * Manual/admin entry point for Workspace provisioning.
 *
 * Thin: resolves the Company on the platform connection and delegates to the single
 * ProvisionWorkspaceAction. Contains no provisioning logic of its own.
 */
class ProvisionWorkspaceCommand extends Command
{
    protected $signature = 'workspace:provision {company : Company ULID or slug}';

    protected $description = 'Provision the Workspace database for a Company (create, migrate, verify, activate)';

    public function handle(ProvisionWorkspaceAction $provisionWorkspace): int
    {
        $identifier = (string) $this->argument('company');

        $company = $this->resolveCompany($identifier);

        if ($company === null) {
            $this->error("Company not found for [{$identifier}].");

            return self::FAILURE;
        }

        try {
            $company = $provisionWorkspace->execute($company);
        } catch (WorkspaceProvisioningException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Workspace provisioned for company [{$company->id}]; status: {$company->status->value}.");

        return self::SUCCESS;
    }

    private function resolveCompany(string $identifier): ?Company
    {
        return Company::on('platform')
            ->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }
}
