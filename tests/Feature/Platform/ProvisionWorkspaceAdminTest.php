<?php

namespace Tests\Feature\Platform;

use App\Models\Company;
use App\Models\PlatformRole;
use App\Models\PlatformUser;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * Provisioning a Company also bootstraps its first Workspace Admin and surfaces the
 * one-time temporary password (1F-E).
 *
 * The Platform Admin supplies the workspace admin's name + email at provision time; the
 * system generates a temporary password, stores only its Argon2id hash in the tenant
 * database, and flashes the plaintext exactly once to the Company Show page.
 */
class ProvisionWorkspaceAdminTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This class defines setUp(), which shadows the trait's setUp(); invoke the
        // trait's workspace wiring explicitly so $workspaceDatabases is initialized.
        $this->setUpWorkspaces();

        // Server-only here; the Stage 5 Inertia page files are asserted elsewhere.
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    protected function tearDown(): void
    {
        $this->tearDownWorkspaces();

        parent::tearDown();
    }

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

    public function test_provisioning_creates_the_initial_workspace_admin_and_flashes_the_temp_password_once(): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create();
        $this->workspaceDatabases->trackExisting($company->database_name);

        $response = $this->actingAs($admin, 'platform')
            ->post(route('platform.companies.provision', $company), [
                'workspace_admin_name' => 'Acme Admin',
                'workspace_admin_email' => 'admin@acme.test',
            ]);

        $response->assertRedirect(route('platform.companies.show', $company));
        $response->assertSessionHas('success');

        // The one-time credential is flashed with the email and a non-empty temp password.
        $flashed = session('workspace_admin');
        $this->assertIsArray($flashed);
        $this->assertSame('admin@acme.test', $flashed['email']);
        $this->assertNotEmpty($flashed['temporary_password']);

        // The workspace admin exists in the tenant database, flagged for first-login change.
        $company->refresh()->run(function () {
            $user = WorkspaceUser::query()->where('email', 'admin@acme.test')->firstOrFail();
            $this->assertTrue($user->must_change_password);
            $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
        });
    }

    public function test_temporary_password_is_not_shown_again_on_a_later_view_of_show(): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create();
        $this->workspaceDatabases->trackExisting($company->database_name);

        $this->actingAs($admin, 'platform')
            ->post(route('platform.companies.provision', $company), [
                'workspace_admin_name' => 'Acme Admin',
                'workspace_admin_email' => 'admin@acme.test',
            ]);

        // A fresh request to the Show page has no flashed credential (flash is one-shot).
        $this->actingAs($admin, 'platform')
            ->get(route('platform.companies.show', $company))
            ->assertOk();

        $this->assertNull(session('workspace_admin'));
    }

    public function test_provision_requires_workspace_admin_name_and_email(): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create();

        $this->actingAs($admin, 'platform')
            ->from(route('platform.companies.show', $company))
            ->post(route('platform.companies.provision', $company), [])
            ->assertSessionHasErrors(['workspace_admin_name', 'workspace_admin_email']);

        // Nothing provisioned: company stays pending.
        $this->assertSame('pending', $company->fresh()->status->value);
    }
}
