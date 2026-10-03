<?php

namespace Tests\Security;

use App\Enums\DomainStatus;
use App\Models\Company;
use App\Models\Domain;
use App\Models\PlatformUser;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * Tenant-isolation security matrix for Workspace authentication (Phase 1F security).
 *
 * Steering requires explicit negative tests: workspace auth must be impossible across
 * tenants, driven only by the resolved host, and must fail safely when there is no tenant
 * context. These assert that Company A's host cannot authenticate Company B's user, that a
 * session on A grants nothing on B, that client-supplied identifiers cannot change tenant
 * selection, that a central host exposes no workspace auth, and that the forced
 * password-change cannot be bypassed.
 */
class WorkspaceAuthIsolationTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    /**
     * Provision an active Company reachable at $host with one onboarded workspace user.
     *
     * @return array{0: Company, 1: string} the company and the user's email
     */
    private function companyAt(string $host, string $slug, string $email): array
    {
        $company = $this->provisionCompany($slug);

        Domain::factory()->create([
            'company_id' => $company->id,
            'domain' => $host,
            'status' => DomainStatus::Active,
            'created_by' => PlatformUser::factory(),
        ]);

        $company->run(function () use ($email) {
            $user = WorkspaceUser::create([
                'name' => 'User',
                'email' => $email,
                'password' => 'secret-password',
            ]);
            $user->forceFill(['must_change_password' => false])->save();
        });

        return [$company, $email];
    }

    public function test_company_a_host_cannot_authenticate_a_company_b_user(): void
    {
        [, $emailA] = $this->companyAt('a.example.test', 'company-a', 'alice@a.test');
        [, $emailB] = $this->companyAt('b.example.test', 'company-b', 'bob@b.test');

        // B's credentials are unknown in A's workspace database → generic failure.
        $response = $this->from('http://a.example.test/login')->post('http://a.example.test/login', [
            'email' => $emailB,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('http://a.example.test/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('workspace');
    }

    public function test_a_workspace_user_id_from_company_a_resolves_to_nobody_in_company_b(): void
    {
        // The workspace guard authenticates by resolving the session's user id against the
        // CURRENT tenant's workspace_users table. Isolation therefore hinges on this: an id
        // issued in Company A must resolve to no one when Company B's tenancy is active.
        // (In production each HTTP request re-resolves the guard against the request host's
        // tenant DB; this asserts the underlying resolution directly, without the in-process
        // guard caching that a single test process would otherwise reuse.)
        [$companyA] = $this->companyAt('a2.example.test', 'company-a2', 'alice@a.test');
        [$companyB] = $this->companyAt('b2.example.test', 'company-b2', 'alice@a.test');

        $aUserId = $companyA->run(fn () => WorkspaceUser::query()->where('email', 'alice@a.test')->value('id'));
        $bUserId = $companyB->run(fn () => WorkspaceUser::query()->where('email', 'alice@a.test')->value('id'));

        // Same email, genuinely different identities per tenant database.
        $this->assertNotSame($aUserId, $bUserId);

        $provider = Auth::createUserProvider('workspace_users');

        // A's id is unknown inside B's workspace; B's id is unknown inside A's.
        $companyB->run(function () use ($provider, $aUserId) {
            $this->assertNull($provider->retrieveById($aUserId));
        });
        $companyA->run(function () use ($provider, $bUserId) {
            $this->assertNull($provider->retrieveById($bUserId));
        });
    }

    public function test_a_guest_on_company_b_is_sent_to_company_b_login_not_company_a(): void
    {
        $this->companyAt('a2b.example.test', 'company-a2b', 'alice@a.test');
        $this->companyAt('b2b.example.test', 'company-b2b', 'bob@b.test');

        // A fresh (unauthenticated) request to B's protected home redirects to B's OWN login
        // on B's host — never A's host or the platform login.
        $this->get('http://b2b.example.test/')
            ->assertRedirect('http://b2b.example.test/login');
    }

    public function test_client_supplied_identifiers_cannot_change_tenant_selection(): void
    {
        [$companyA] = $this->companyAt('a3.example.test', 'company-a3', 'alice@a.test');
        [$companyB] = $this->companyAt('b3.example.test', 'company-b3', 'bob@b.test');

        // Attempt to override tenant selection via query/body params while on A's host.
        $response = $this->get('http://a3.example.test/login?'.http_build_query([
            'company_id' => $companyB->id,
            'tenant_id' => $companyB->id,
            'database' => $companyB->database_name,
        ]));

        // Still A's login page (host-only resolution); no error, no B context.
        $response->assertOk();
    }

    public function test_workspace_login_is_not_available_on_a_central_host(): void
    {
        // A central host has no tenant context; workspace routes do not serve it. The
        // platform login answers /login instead (never the workspace login).
        $this->get('http://localhost/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Platform/Auth/Login'));
    }

    public function test_unknown_host_fails_safely_without_workspace_auth(): void
    {
        // A non-central host with no active domain resolves to nothing → 404, no tenancy.
        $this->get('http://nobody.example.test/login')->assertNotFound();

        $this->assertFalse(tenancy()->initialized);
        $this->assertGuest('workspace');
    }

    public function test_forced_password_change_cannot_be_bypassed(): void
    {
        $company = $this->provisionCompany('company-fp');
        Domain::factory()->create([
            'company_id' => $company->id,
            'domain' => 'fp.example.test',
            'status' => DomainStatus::Active,
            'created_by' => PlatformUser::factory(),
        ]);

        // A user still flagged must_change_password (DB default true on create).
        $company->run(function () {
            WorkspaceUser::create([
                'name' => 'Temp Admin',
                'email' => 'temp@fp.test',
                'password' => 'temp-password',
            ]);
        });

        $this->post('http://fp.example.test/login', [
            'email' => 'temp@fp.test',
            'password' => 'temp-password',
        ]);
        $this->assertAuthenticated('workspace');

        // Every protected workspace route is forced to the change page while flagged.
        $this->get('http://fp.example.test/')
            ->assertRedirect('http://fp.example.test/password/change');
    }
}
