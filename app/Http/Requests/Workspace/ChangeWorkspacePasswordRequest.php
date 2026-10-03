<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the forced first-login password change for a Workspace User (1F-F).
 *
 * Requires the current (temporary) password plus a confirmed new password that meets
 * the default strength rules. The controller verifies the current password against the
 * stored Argon2id hash and stores the new one via the Hash abstraction — no custom hashing.
 */
class ChangeWorkspacePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
