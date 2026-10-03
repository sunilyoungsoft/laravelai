<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates the Workspace login form and performs the credential attempt against the
 * `workspace` guard (1F-C).
 *
 * Mirrors the Platform LoginRequest: a single generic error keyed to `email` so the
 * response never discloses whether the email or password was wrong, plus rate limiting.
 * The throttle key is scoped by the resolved tenant so one Company's attempts never
 * throttle another's. This request runs only inside tenant context (the route sits
 * behind tenant.resolve), so the `workspace` guard resolves against this Company's own
 * database.
 */
class WorkspaceLoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials against the `workspace` guard.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::guard('workspace')->attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Rate-limit key, scoped by the resolved tenant so attempts are isolated per Company.
     */
    public function throttleKey(): string
    {
        $tenantKey = tenant()?->getTenantKey() ?? 'central';

        return Str::transliterate($tenantKey.'|'.Str::lower($this->string('email')).'|'.$this->ip());
    }
}
