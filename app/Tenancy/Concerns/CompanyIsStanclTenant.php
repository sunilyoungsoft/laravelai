<?php

namespace App\Tenancy\Concerns;

use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\TenantRun;

/**
 * Lets Company satisfy Stancl's tenant contract without a tenants table.
 *
 * database_name remains the workspace database name. Stancl internals are not persisted.
 */
trait CompanyIsStanclTenant
{
    use HasDatabase;
    use TenantRun;

    public function getTenantKeyName(): string
    {
        return $this->getKeyName();
    }

    public function getTenantKey(): mixed
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    /**
     * Prefix that does not match real company columns.
     * DatabaseConfig uses it to detect optional tenancy_db_* connection overrides.
     */
    public function internalPrefix(): string
    {
        return 'tenancy_';
    }

    public function getInternal(string $key): mixed
    {
        if ($key === 'db_name') {
            return $this->database_name;
        }

        return null;
    }

    /**
     * Stancl credentials are not stored on Company.
     * database_name is owned by CompanyService.
     */
    public function setInternal(string $key, mixed $value): static
    {
        return $this;
    }
}
