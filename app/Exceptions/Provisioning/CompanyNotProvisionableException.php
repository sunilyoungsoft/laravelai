<?php

namespace App\Exceptions\Provisioning;

use App\Enums\CompanyStatus;
use App\Models\Company;

/**
 * Thrown when provisioning is requested for a Company whose status does not permit it
 * (active, suspended, deactivated, or a concurrent run already in progress).
 *
 * This is a refusal that leaves Company state unchanged — it is never a partial provisioning.
 */
class CompanyNotProvisionableException extends WorkspaceProvisioningException
{
    public static function forStatus(Company $company, CompanyStatus $status): self
    {
        return new self(
            "Company [{$company->id}] cannot be provisioned from status [{$status->value}]."
        );
    }

    public static function concurrentRun(Company $company): self
    {
        return new self(
            "Company [{$company->id}] is already provisioning; refusing a concurrent run."
        );
    }
}
