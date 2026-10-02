<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin controller for Platform admin session authentication on the `platform` guard.
 *
 * The credential attempt and the generic (non-disclosing) error live in LoginRequest;
 * this controller only renders the form, regenerates/invalidates the session, and
 * redirects. No registration, password-reset, or 2FA surfaces exist (Req A5).
 */
class PlatformLoginController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Platform/Auth/Login');
    }

    /**
     * Attempt a login (delegated to the Form Request) and land on the Dashboard.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('platform.dashboard'));
    }

    /**
     * End the Platform session and return to the login page.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
