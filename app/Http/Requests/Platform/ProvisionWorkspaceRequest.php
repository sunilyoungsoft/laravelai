<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Boundary validation for triggering Workspace provisioning (1F-E).
 *
 * Captures the first Workspace Admin's identity (name + email) supplied by the Platform
 * Admin. The temporary password is NOT accepted from input — it is generated server-side
 * by CreateInitialWorkspaceAdminAction and shown once. Authorization (`provision` on the
 * Company) is enforced in the controller via CompanyPolicy.
 */
class ProvisionWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'workspace_admin_name' => ['required', 'string', 'max:255'],
            'workspace_admin_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
