<?php

namespace Tests\Feature\Workspace;

use App\Actions\Workspace\SeedWorkspaceRbacAction;
use App\Enums\DomainStatus;
use App\Models\Company;
use App\Models\Domain;
use App\Models\PlatformUser;
use App\Models\WorkspaceRole;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * Workspace session authentication on a resolved Company host (1F-C, 1F-F).
 *
 * Covers login success → workspace home, generic non-disclosing credential errors,
 * logout, the forced first-login password change, and that the workspace login is only
 * reachable on a host that resolves to an active Company.
 */
class WorkspaceAuthTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    private string $host = 'acme.example.test';

    private function provisionCompanyWithHost(string $host): Company
    {
        $company = $this->provisionCompany();

        Domain::factory()->create([
            'company_id' => $company->id,
            'domain' => $host,
            'status' => DomainStatus::Active,
            'created_by' => PlatformUser::factory(),
        ]);

        return $company;
    }

    /**
     * Create an onboarded workspace user inside the Company's tenant database.
     *
     * Mirrors the real provisioning outcome: baseline RBAC is seeded and the user holds the
     * workspace-admin system role, so they carry workspace.access (now required to reach the
     * workspace home). These tests exercise the auth flow — login, logout, forced change — on
     * a properly authorized user, not the authorization matrix itself (that lives in
     * WorkspaceAuthorizationTest).
     */
    private function createWorkspaceUser(Company $company, array $attributes = []): void
    {
        $company->run(function () use ($attributes) {
            app(SeedWorkspaceRbacAction::class)->execute();

            $user = WorkspaceUser::create(array_merge([
                'name' => 'Workspace User',
                'email' => 'user@acme.test',
                'password' => 'secret-password',
            ], $attributes));

            $user->roles()->syncWithoutDetaching([
                WorkspaceRole::query()->where('slug', 'workspace-admin')->firstOrFail()->id,
            ]);

            // must_change_password is not fillable; it defaults to true in the DB. Unless a
            // test explicitly wants the forced-change state, treat the user as onboarded.
            $user->forceFill([
                'must_change_password' => $attributes['must_change_password'] ?? false,
            ])->save();
        });
    }

    public function test_authenticated_workspace_user_sees_the_home_landing(): void
    {
        $company = $this->provisionCompanyWithHost($this->host);
        $this->createWorkspaceUser($company);

        $this->post("http://{$this->host}/login", [
            'email' => 'user@acme.test',
            'password' => 'secret-password',
        ]);

        $this->get("http://{$this->host}/")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workspace/Home')
                ->where('workspaceAuth.user.email', 'user@acme.test'));
    }

    public function test_login_page_is_reachable_on_a_resolved_company_host(): void
    {
        $this->provisionCompanyWithHost($this->host);

        $this->get("http://{$this->host}/login")->assertOk();
    }

    public function test_valid_credentials_log_in_and_redirect_to_workspace_home(): void
    {
        $company = $this->provisionCompanyWithHost($this->host);
        $this->createWorkspaceUser($company);

        $response = $this->post("http://{$this->host}/login", [
            'email' => 'user@acme.test',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('workspace.home'));
        $this->assertAuthenticated('workspace');
    }

    public function test_invalid_credentials_return_a_generic_error_without_field_disclosure(): void
    {
        $company = $this->provisionCompanyWithHost($this->host);
        $this->createWorkspaceUser($company);

        $response = $this->from("http://{$this->host}/login")->post("http://{$this->host}/login", [
            'email' => 'user@acme.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect("http://{$this->host}/login");
        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertFalse(session('errors')->has('password'));
        $this->assertGuest('workspace');
    }

    public function test_logout_ends_the_workspace_session(): void
    {
        $company = $this->provisionCompanyWithHost($this->host);
        $this->createWorkspaceUser($company);

        $this->post("http://{$this->host}/login", [
            'email' => 'user@acme.test',
            'password' => 'secret-password',
        ]);
        $this->assertAuthenticated('workspace');

        $this->post("http://{$this->host}/logout")
            ->assertRedirect("http://{$this->host}/login");

        $this->assertGuest('workspace');
    }

    public function test_guest_is_redirected_to_the_workspace_login(): void
    {
        $this->provisionCompanyWithHost($this->host);

        // The guest redirect is host-aware: a workspace host sends guests to the workspace
        // login on that same host (not the platform login).
        $this->get("http://{$this->host}/")
            ->assertRedirect("http://{$this->host}/login");
    }

    public function test_user_requiring_password_change_is_forced_to_the_change_page(): void
    {
        $company = $this->provisionCompanyWithHost($this->host);
        $this->createWorkspaceUser($company, ['must_change_password' => true]);

        $this->post("http://{$this->host}/login", [
            'email' => 'user@acme.test',
            'password' => 'secret-password',
        ]);

        // Any workspace route redirects to the change page while the flag is set.
        $this->get("http://{$this->host}/")
            ->assertRedirect(route('workspace.password.change'));

        // The change page itself is reachable.
        $this->get("http://{$this->host}/password/change")->assertOk();
    }

    public function test_changing_the_password_clears_the_flag_and_rehashes(): void
    {
        $company = $this->provisionCompanyWithHost($this->host);
        $this->createWorkspaceUser($company, ['must_change_password' => true]);

        $this->post("http://{$this->host}/login", [
            'email' => 'user@acme.test',
            'password' => 'secret-password',
        ]);

        $this->post("http://{$this->host}/password/change", [
            'current_password' => 'secret-password',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect(route('workspace.home'));

        $company->run(function () {
            $user = WorkspaceUser::query()->where('email', 'user@acme.test')->firstOrFail();
            $this->assertFalse($user->must_change_password);
            $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
            $this->assertTrue(Hash::check('new-strong-password', $user->password));
        });

        // After clearing the flag, the workspace home is reachable.
        $this->get("http://{$this->host}/")->assertOk();
    }

    public function test_wrong_current_password_is_rejected_on_change(): void
    {
        $company = $this->provisionCompanyWithHost($this->host);
        $this->createWorkspaceUser($company, ['must_change_password' => true]);

        $this->post("http://{$this->host}/login", [
            'email' => 'user@acme.test',
            'password' => 'secret-password',
        ]);

        $this->from("http://{$this->host}/password/change")
            ->post("http://{$this->host}/password/change", [
                'current_password' => 'not-the-current-password',
                'password' => 'new-strong-password',
                'password_confirmation' => 'new-strong-password',
            ])
            ->assertSessionHasErrors('current_password');

        $company->run(function () {
            $this->assertTrue(
                WorkspaceUser::query()->where('email', 'user@acme.test')->firstOrFail()->must_change_password
            );
        });
    }
}
