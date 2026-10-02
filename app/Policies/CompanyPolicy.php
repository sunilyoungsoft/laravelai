<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\PlatformUser;

/**
 * Server-authoritative authorization for Platform admin Company management.
 *
 * Every ability is granted only to a Platform User holding the active Admin
 * system role. A guest (null user) is denied: Laravel's gate forbids before
 * invoking the policy when no authenticated user is present, and the typed
 * PlatformUser signatures keep that contract explicit.
 */
class CompanyPolicy
{
    public function viewAny(PlatformUser $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(PlatformUser $user, Company $company): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(PlatformUser $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function provision(PlatformUser $user, Company $company): bool
    {
        return $user->isPlatformAdmin();
    }

    public function manageDomains(PlatformUser $user, Company $company): bool
    {
        return $user->isPlatformAdmin();
    }
}
