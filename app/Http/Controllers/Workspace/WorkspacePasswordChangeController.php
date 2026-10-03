<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\ChangeWorkspacePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forced first-login password change for Workspace Users (1F-F).
 *
 * Verifies the current (temporary) password against the stored Argon2id hash, stores the
 * new password through the Hash abstraction (the model's `hashed` cast → Argon2id), clears
 * must_change_password, and returns to the workspace home. All other workspace routes are
 * blocked by EnsureWorkspacePasswordChanged until this completes.
 */
class WorkspacePasswordChangeController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Workspace/Auth/ChangePassword');
    }

    public function update(ChangeWorkspacePasswordRequest $request): RedirectResponse
    {
        $user = Auth::guard('workspace')->user();

        if (! Hash::check((string) $request->validated('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.password'),
            ]);
        }

        $user->forceFill([
            // The `hashed` cast re-hashes with the configured Argon2id driver.
            'password' => (string) $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('workspace.home');
    }
}
