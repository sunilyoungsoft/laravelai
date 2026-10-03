<?php

namespace App\Actions\Workspace;

/**
 * Typed, readonly input for creating a Company's first Workspace Admin (1F-E).
 *
 * Carries only the identity the Platform Admin supplies at provisioning time. The
 * temporary password is NOT part of the input — it is generated inside the Action so
 * no caller can set or observe it except via the one-time return value.
 */
final readonly class CreateInitialWorkspaceAdminData
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
