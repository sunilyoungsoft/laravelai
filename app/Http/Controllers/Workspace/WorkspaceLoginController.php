<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\WorkspaceLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin controller for Workspace (tenant) session authentication on the `workspace` guard
 * (1F-C). Served only on a resolved Company host (behind tenant.resolve), so the guard
 * authenticates against this Company's own workspace database.
 *
 * The credential attempt and the generic (non-disclosing) error live in
 * WorkspaceLoginRequest; this controller only renders the form, regenerates/invalidates
 * the session, records the login, and redirects. A user flagged must_change_password is
 * routed to the forced change page by EnsureWorkspacePasswordChanged (1F-F).
 */
class WorkspaceLoginController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Workspace/Auth/Login');
    }

    public function login(WorkspaceLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::guard('workspace')->user();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('workspace.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('workspace')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('workspace.login');
    }
}
