<?php

namespace App\Actions\Workspace;

use App\Models\WorkspaceUser;

/**
 * Result of creating the initial Workspace Admin.
 *
 * Carries the created user together with the generated plaintext temporary password
 * so the caller can display it exactly once. The plaintext lives ONLY in this transient
 * return value — it is never persisted (only its Argon2id hash is stored) and must never
 * be logged. Callers surface it in a one-shot flash and then discard it.
 */
final readonly class InitialWorkspaceAdminResult
{
    public function __construct(
        public WorkspaceUser $user,
        public string $temporaryPassword,
    ) {}
}
