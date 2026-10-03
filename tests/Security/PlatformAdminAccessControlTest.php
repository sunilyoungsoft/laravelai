<?php

namespace Tests\Security;

use App\Enums\DomainType;
use App\Models\Company;
use App\Models\PlatformRole;
use App\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Negative authorization matrix for every Stage 3 Platform admin action (Req B2, security
 * steering: tenant/platform isolation must be continuously tested, never assumed).
 *
 * Steering requires explicit negative tests: a user WITHOUT the Admin system role, and a guest,
 * must be denied EVERY protected action. This test asserts the full matrix in one place:
 *  - an authenticated NON-admin platform user  → 403 (CompanyPolicy denies via isPlatformAdmin)
 *  - a guest (unauthenticated)                  → redirect to the platform login
 *
 * Actions covered: dashboard, companies index, create, store, show, provision, domain store,
 * domain update. The happy-path behavior lives in
 * tests/Feature/Platform/PlatformAdminControllersTest.php.
 */
class PlatformAdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A Platform User WITHOUT the Admin system role (an ordinary role only). isPlatformAdmin()
     * returns false, so CompanyPolicy forbids every ability and the controllers 403.
     */
    private function nonAdminUser(): PlatformUser
    {
        $user = PlatformUser::factory()->create();
        $role = PlatformRole::factory()->create();

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    /**
     * Each protected action as [httpMethod, routeName, needsCompany, payload].
     *
     * @return array<string, array{string, string, bool, array<string, mixed>}>
     */
    public static function protectedActions(): array
    {
        return [
            'dashboard' => ['get', 'platform.dashboard', false, []],
            'companies.index' => ['get', 'platform.companies.index', false, []],
            'companies.create' => ['get', 'platform.companies.create', false, []],
            'companies.store' => ['post', 'platform.companies.store', false, ['name' => 'X', 'slug' => 'x-co']],
            'companies.show' => ['get', 'platform.companies.show', true, []],
            'companies.provision' => ['post', 'platform.companies.provision', true, ['workspace_admin_name' => 'A', 'workspace_admin_email' => 'a@example.test']],
            'companies.domain.store' => ['post', 'platform.companies.domain.store', true, ['domain' => 'a.example.test', 'type' => DomainType::Custom->value]],
            'companies.domain.update' => ['put', 'platform.companies.domain.update', true, ['domain' => 'b.example.test', 'type' => DomainType::Custom->value]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('protectedActions')]
    public function test_non_admin_platform_user_is_forbidden(string $method, string $routeName, bool $needsCompany, array $payload): void
    {
        $user = $this->nonAdminUser();
        $url = $this->routeFor($routeName, $needsCompany);

        $this->actingAs($user, 'platform')
            ->{$method}($url, $payload)
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('protectedActions')]
    public function test_guest_is_redirected_to_login(string $method, string $routeName, bool $needsCompany, array $payload): void
    {
        $url = $this->routeFor($routeName, $needsCompany);

        $this->{$method}($url, $payload)
            ->assertRedirect(route('platform.login'));
    }

    /**
     * Resolve the URL for a route, creating a bound Company only when the route needs one.
     */
    private function routeFor(string $routeName, bool $needsCompany): string
    {
        if (! $needsCompany) {
            return route($routeName);
        }

        return route($routeName, Company::factory()->create());
    }
}
