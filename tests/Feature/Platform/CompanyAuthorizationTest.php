<?php

namespace Tests\Feature\Platform;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Company;
use App\Models\PlatformRole;
use App\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Authorization layer tests for Platform admin Company management (Req B1–B3).
 *
 * Covers:
 *  - PlatformUser::isPlatformAdmin() (active Admin system role only)
 *  - CompanyPolicy abilities (viewAny, view, create, provision, manageDomains):
 *    each ALLOWS a Platform admin and FORBIDS a non-admin user and a guest.
 *  - The Inertia shared auth.can map (companies.viewAny, companies.create).
 */
class CompanyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a Platform User holding the active Admin system role
     * (slug=admin, is_system=true, status=1). is_system/status are guarded,
     * so the factory sets them directly (bypassing mass assignment).
     */
    private function platformAdmin(): PlatformUser
    {
        $user = PlatformUser::factory()->create();
        $role = PlatformRole::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'status' => 1,
        ]);

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    /**
     * Build a Platform User with an ordinary (non-admin) role.
     */
    private function nonAdminUser(): PlatformUser
    {
        $user = PlatformUser::factory()->create();
        $role = PlatformRole::factory()->create();

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    // ---------------------------------------------------------------------
    // isPlatformAdmin() helper (Req B1)
    // ---------------------------------------------------------------------

    public function test_is_platform_admin_is_true_for_active_admin_system_role(): void
    {
        $this->assertTrue($this->platformAdmin()->isPlatformAdmin());
    }

    public function test_is_platform_admin_is_false_for_a_non_admin_role(): void
    {
        $this->assertFalse($this->nonAdminUser()->isPlatformAdmin());
    }

    public function test_is_platform_admin_is_false_for_an_inactive_admin_role(): void
    {
        $user = PlatformUser::factory()->create();
        $role = PlatformRole::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'status' => 0,
        ]);

        $user->roles()->attach($role->id);

        $this->assertFalse($user->fresh()->isPlatformAdmin());
    }

    public function test_is_platform_admin_is_false_for_a_user_with_no_roles(): void
    {
        $user = PlatformUser::factory()->create();

        $this->assertFalse($user->fresh()->isPlatformAdmin());
    }

    // ---------------------------------------------------------------------
    // CompanyPolicy abilities (Req B1, B2)
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function companyAbilities(): array
    {
        return [
            'viewAny' => ['viewAny'],
            'view' => ['view'],
            'create' => ['create'],
            'provision' => ['provision'],
            'manageDomains' => ['manageDomains'],
        ];
    }

    #[DataProvider('companyAbilities')]
    public function test_ability_allows_a_platform_admin(string $ability): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create();

        $this->assertTrue(
            Gate::forUser($admin)->allows($ability, [Company::class, $company]),
            "Ability [{$ability}] must be allowed for a Platform admin."
        );
    }

    #[DataProvider('companyAbilities')]
    public function test_ability_forbids_a_non_admin_user(string $ability): void
    {
        $user = $this->nonAdminUser();
        $company = Company::factory()->create();

        $this->assertFalse(
            Gate::forUser($user)->allows($ability, [Company::class, $company]),
            "Ability [{$ability}] must be forbidden for a non-admin user."
        );
    }

    #[DataProvider('companyAbilities')]
    public function test_ability_forbids_a_guest(string $ability): void
    {
        $company = Company::factory()->create();

        // A guest (null user) must be denied: the gate forbids before the
        // typed PlatformUser policy method is ever invoked.
        $this->assertFalse(
            Gate::forUser(null)->allows($ability, [Company::class, $company]),
            "Ability [{$ability}] must be forbidden for a guest."
        );
    }

    // ---------------------------------------------------------------------
    // Inertia shared auth.can map (Req B3)
    // ---------------------------------------------------------------------

    public function test_shared_auth_can_map_is_true_for_an_authenticated_admin(): void
    {
        $admin = $this->platformAdmin();

        $can = $this->actingAs($admin, 'platform')->sharedAuthCan();

        $this->assertTrue($can['companies.viewAny']);
        $this->assertTrue($can['companies.create']);
    }

    public function test_shared_auth_can_map_is_false_for_a_non_admin_user(): void
    {
        $user = $this->nonAdminUser();

        $can = $this->actingAs($user, 'platform')->sharedAuthCan();

        $this->assertFalse($can['companies.viewAny']);
        $this->assertFalse($can['companies.create']);
    }

    public function test_shared_auth_can_map_is_false_for_a_guest(): void
    {
        $can = $this->sharedAuthCan();

        $this->assertFalse($can['companies.viewAny']);
        $this->assertFalse($can['companies.create']);
    }

    /**
     * Resolve the Inertia shared auth.can map through the real middleware,
     * exercising the HandleInertiaRequests::share() wiring end to end.
     *
     * @return array{companies.viewAny: bool, companies.create: bool}
     */
    private function sharedAuthCan(): array
    {
        $middleware = app(HandleInertiaRequests::class);
        $shared = $middleware->share(request());

        $can = value($shared['auth']['can']);

        return [
            'companies.viewAny' => (bool) $can['companies.viewAny'],
            'companies.create' => (bool) $can['companies.create'],
        ];
    }
}
