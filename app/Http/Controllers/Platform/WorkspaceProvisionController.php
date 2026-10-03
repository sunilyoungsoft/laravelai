<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\ProvisionWorkspaceAction;
use App\Actions\Workspace\CreateInitialWorkspaceAdminAction;
use App\Actions\Workspace\CreateInitialWorkspaceAdminData;
use App\Exceptions\Provisioning\CompanyNotProvisionableException;
use App\Exceptions\Provisioning\WorkspaceProvisioningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ProvisionWorkspaceRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Trigger synchronous Workspace provisioning (or a retry) for a Company and create the
 * first Workspace Admin (F2, F3, 1E-E, 1F-E).
 *
 * Thin controller: it authorizes `provision` on the specific Company, then delegates all
 * provisioning logic to the single ProvisionWorkspaceAction, run SYNCHRONOUSLY in the request
 * (1E-E — the admin UI deliberately chose sync; the queued ProvisionWorkspaceJob path stays
 * unproven per project-status and is NOT used here). It contains no provisioning logic itself.
 *
 * The Action throws exactly two exceptions, which this controller turns into user-facing
 * flashes instead of a 500:
 *  - CompanyNotProvisionableException — invalid status / concurrent run / active company
 *    rejected. Company state is left unchanged.
 *  - WorkspaceProvisioningException — provisioning failed; the Action has already marked the
 *    Company `provisioning_failed` and logged, so a Retry is possible.
 *
 * Catch order matters: CompanyNotProvisionableException extends WorkspaceProvisioningException,
 * so the refusal (more specific) is caught before the failure (base).
 *
 * After a successful provision, the initial Workspace Admin is created INSIDE the new tenant
 * context (CreateInitialWorkspaceAdminAction). The generated temporary password is flashed to
 * the Company Show page exactly once (one-shot flash, never persisted, never logged); the
 * admin must change it on first login (1F-F).
 */
class WorkspaceProvisionController extends Controller
{
    /**
     * Provision or retry the Workspace database for the given Company, then bootstrap its
     * first Workspace Admin.
     */
    public function store(
        ProvisionWorkspaceRequest $request,
        Company $company,
        ProvisionWorkspaceAction $provisionWorkspace,
        CreateInitialWorkspaceAdminAction $createInitialAdmin,
    ): RedirectResponse {
        $this->authorize('provision', $company);

        try {
            $provisionWorkspace->execute($company, $request->user());
        } catch (CompanyNotProvisionableException $exception) {
            return redirect()
                ->route('platform.companies.show', $company)
                ->with('error', 'This company cannot be provisioned in its current state.');
        } catch (WorkspaceProvisioningException $exception) {
            return redirect()
                ->route('platform.companies.show', $company)
                ->with('error', 'Workspace provisioning failed. The company is marked as provisioning failed; you can retry.');
        }

        $temporaryPassword = $this->createInitialWorkspaceAdmin(
            $company,
            $createInitialAdmin,
            (string) $request->validated('workspace_admin_name'),
            (string) $request->validated('workspace_admin_email'),
        );

        $redirect = redirect()
            ->route('platform.companies.show', $company)
            ->with('success', 'Workspace provisioned.');

        // One-shot flash: shown once on the Show page, never persisted.
        if ($temporaryPassword !== null) {
            $redirect->with('workspace_admin', [
                'email' => (string) $request->validated('workspace_admin_email'),
                'temporary_password' => $temporaryPassword,
            ]);
        }

        return $redirect;
    }

    /**
     * Create the first Workspace Admin inside the Company's tenant context and return the
     * one-time temporary password. Returns null when an admin already exists (e.g. on a
     * retry of an already-bootstrapped workspace), so no stale credential is surfaced.
     */
    private function createInitialWorkspaceAdmin(
        Company $company,
        CreateInitialWorkspaceAdminAction $createInitialAdmin,
        string $name,
        string $email,
    ): ?string {
        return $company->run(function () use ($createInitialAdmin, $name, $email): ?string {
            try {
                return $createInitialAdmin
                    ->execute(new CreateInitialWorkspaceAdminData($name, $email))
                    ->temporaryPassword;
            } catch (Throwable $exception) {
                // The workspace already has a user (bootstrap is one-time) — provisioning
                // still succeeded, so do not fail the request; simply surface no password.
                return null;
            }
        });
    }
}
