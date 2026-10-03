<?php

namespace App\Actions\Workspace;

use App\Models\WorkspaceRole;
use App\Models\WorkspaceUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Create the first Workspace Admin for a Company's workspace (1F-E).
 *
 * MUST run inside tenant context (the caller enters it, e.g. via $company->run(...)),
 * because WorkspaceUser resolves on the dynamic `tenant` connection. The Action:
 *  - generates a cryptographically secure temporary password (Str::password),
 *  - stores only its Argon2id hash (via the model's `hashed` cast — no custom hashing),
 *  - marks must_change_password so the admin is forced to set a permanent password on
 *    first login (1F-F),
 *  - returns the plaintext temporary password exactly once (never persisted, never
 *    logged) so the caller can display it a single time to the Platform Admin.
 *
 * It refuses to run if the workspace already has any user — this is a one-time bootstrap
 * of the first admin, mirroring platform:create-admin's single-bootstrap guarantee.
 */
class CreateInitialWorkspaceAdminAction
{
    private const TEMPORARY_PASSWORD_LENGTH = 20;

    public function __construct(
        private readonly SeedWorkspaceRbacAction $seedWorkspaceRbac,
    ) {}

    public function execute(CreateInitialWorkspaceAdminData $data): InitialWorkspaceAdminResult
    {
        $this->ensureNoExistingWorkspaceUser();

        $validated = $this->validate($data);

        // Ensure the baseline RBAC (workspace-admin role + proof permissions) exists before
        // attaching the role. Idempotent, so a workspace already seeded is unaffected (1G).
        $this->seedWorkspaceRbac->execute();

        $temporaryPassword = Str::password(self::TEMPORARY_PASSWORD_LENGTH);

        $user = new WorkspaceUser;
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // The `hashed` cast hashes this with the configured Argon2id driver.
            'password' => $temporaryPassword,
        ]);
        $user->must_change_password = true;
        $user->status = 1;
        $user->save();

        // Grant the initial admin the protected Workspace Admin system role (1F-E + 1G).
        $adminRole = WorkspaceRole::where('slug', 'workspace-admin')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return new InitialWorkspaceAdminResult(
            user: $user->refresh(),
            temporaryPassword: $temporaryPassword,
        );
    }

    /**
     * This is a first-admin bootstrap: refuse if the workspace already has any user
     * (including soft-deleted, so a reserved identity is not silently replaced).
     */
    private function ensureNoExistingWorkspaceUser(): void
    {
        $exists = WorkspaceUser::withTrashed()->exists();

        if ($exists) {
            throw new RuntimeException(
                'A Workspace user already exists. The initial Workspace Admin is created only once.'
            );
        }
    }

    /**
     * @return array{name: string, email: string}
     */
    private function validate(CreateInitialWorkspaceAdminData $data): array
    {
        $validator = Validator::make(
            ['name' => $data->name, 'email' => $data->email],
            [
                'name' => ['required', 'string', 'max:255'],
                // Unique within this tenant database only (the default connection here).
                'email' => ['required', 'string', 'email', 'max:255', 'unique:workspace_users,email'],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{name: string, email: string} $validated */
        $validated = $validator->validated();

        return $validated;
    }
}
