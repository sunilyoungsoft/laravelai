<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces the initial Workspace Admin (and any user flagged `must_change_password`) to
 * set a permanent password before using the workspace (1F-F).
 *
 * While the flag is set, every authenticated workspace route is redirected to the
 * password-change page — except the change-password routes themselves and logout, so
 * the user can complete the change or leave. Once the flag is cleared (on a successful
 * change), normal access resumes.
 */
class EnsureWorkspacePasswordChanged
{
    /**
     * Route names that remain reachable while a password change is pending.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'workspace.password.change',
        'workspace.password.update',
        'workspace.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('workspace')->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($this->isAllowedWhilePending($request)) {
            return $next($request);
        }

        return redirect()->route('workspace.password.change');
    }

    private function isAllowedWhilePending(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return $routeName !== null && in_array($routeName, self::ALLOWED_ROUTES, true);
    }
}
