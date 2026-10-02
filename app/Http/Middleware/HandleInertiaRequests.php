<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\PlatformUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => fn () => $this->platformUser()?->only('id', 'name', 'email'),
                'can' => fn (): array => [
                    'companies.viewAny' => $this->platformUser()?->can('viewAny', Company::class) ?? false,
                    'companies.create' => $this->platformUser()?->can('create', Company::class) ?? false,
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'demo_note_id' => fn () => $request->session()->get('demo_note_id'),
            ],
        ];
    }

    /**
     * The authenticated Platform User, or null for a guest.
     * Platform auth always resolves through the explicit `platform` guard.
     */
    private function platformUser(): ?PlatformUser
    {
        return Auth::guard('platform')->user();
    }
}
