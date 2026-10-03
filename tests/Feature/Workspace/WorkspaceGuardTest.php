<?php

namespace Tests\Feature\Workspace;

use App\Models\WorkspaceUser;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The `workspace` auth guard is registered and backed by WorkspaceUser (1F-C).
 *
 * The application default guard remains `platform` — workspace code must opt in via
 * Auth::guard('workspace').
 */
class WorkspaceGuardTest extends TestCase
{
    public function test_default_guard_is_still_platform(): void
    {
        $this->assertSame('platform', config('auth.defaults.guard'));
    }

    public function test_workspace_guard_resolves_the_workspace_user_provider(): void
    {
        $guard = Auth::guard('workspace');

        $this->assertInstanceOf(SessionGuard::class, $guard);

        $provider = $guard->getProvider();

        $this->assertInstanceOf(EloquentUserProvider::class, $provider);
        $this->assertSame(WorkspaceUser::class, $provider->getModel());
    }
}
