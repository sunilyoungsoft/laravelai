<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\ProvisionWorkspaceAction;
use App\Exceptions\Provisioning\CompanyNotProvisionableException;
use App\Exceptions\Provisioning\WorkspaceProvisioningException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Trigger synchronous Workspace provisioning (or a retry) for a Company (F2, F3, 1E-E).
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
 */
class WorkspaceProvisionController extends Controller
{
    /**
     * Provision or retry the Workspace database for the given Company.
     */
    public function store(Request $request, Company $company, ProvisionWorkspaceAction $provisionWorkspace): RedirectResponse
    {
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

        return redirect()
            ->route('platform.companies.show', $company)
            ->with('success', 'Workspace provisioned.');
    }
}
