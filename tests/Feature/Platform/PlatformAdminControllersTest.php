<?php

namespace Tests\Feature\Platform;

use App\Enums\CompanyStatus;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Company;
use App\Models\Domain;
use App\Models\PlatformRole;
use App\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tenancy\Support\GuardedWorkspaceDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Stage 3 Platform admin controllers (Req C–F).
 *
 * Exercises the thin controllers end to end through their routes (auth:platform,
 * authorized by CompanyPolicy):
 *  - DashboardController          — status counts, soft-deleted excluded (C).
 *  - CompanyController@index      — search + status filter + soft-delete exclusion (D).
 *  - CompanyController@create/@store — form page + pending create, NO provisioning (E).
 *  - CompanyController@show       — company + effective domain + per-company can (F1).
 *  - WorkspaceProvisionController@store — sync provision delegate + status reflect (F2, F3).
 *  - CompanyDomainController@store/@update — add/change effective domain delegate (F4, F5).
 *
 * The cross-cutting negative authorization matrix (non-admin 403 / guest redirect for every
 * action) lives in tests/Security/PlatformAdminAccessControlTest.php.
 */
class PlatformAdminControllersTest extends TestCase
{
    use RefreshDatabase;

    private GuardedWorkspaceDatabase $workspaceDatabases;

    protected function setUp(): void
    {
        parent::setUp();

        // Provisioning tests create real workspace databases via the proven Phase 1D
        // mechanism (the test-only spike flag). Mirror the connection wiring used by
        // tests/Feature/Provisioning so the tenant connection reaches the local MySQL.
        $this->workspaceDatabases = new GuardedWorkspaceDatabase;

        // Stage 3 is server-only; the Stage 5 Inertia page files do not exist yet. Assert on the
        // rendered component name + props without requiring the frontend file to be present.
        config(['inertia.testing.ensure_pages_exist' => false]);

        config([
            'database.connections.workspace.host' => config('database.connections.platform.host'),
            'database.connections.workspace.port' => config('database.connections.platform.port'),
            'database.connections.workspace.username' => config('database.connections.platform.username'),
            'database.connections.workspace.password' => config('database.connections.platform.password'),
        ]);
        DB::purge('workspace');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->workspaceDatabases->dropTracked();

        parent::tearDown();
    }

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

    // ---------------------------------------------------------------------
    // Dashboard — status counts, soft-deleted excluded (Req C1, C2)
    // ---------------------------------------------------------------------

    public function test_dashboard_counts_companies_per_status_and_excludes_soft_deleted(): void
    {
        $admin = $this->platformAdmin();

        Company::factory()->count(2)->create(['status' => CompanyStatus::Pending]);
        Company::factory()->count(3)->create(['status' => CompanyStatus::Active]);
        Company::factory()->create(['status' => CompanyStatus::Suspended]);

        // A soft-deleted company must not be counted anywhere.
        Company::factory()->create(['status' => CompanyStatus::Active])->delete();

        $this->actingAs($admin, 'platform')
            ->get(route('platform.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Dashboard')
                ->where('statusCounts.pending', 2)
                ->where('statusCounts.active', 3)
                ->where('statusCounts.suspended', 1)
                ->where('statusCounts.provisioning', 0)
                ->where('statusCounts.provisioning_failed', 0)
                ->where('statusCounts.deactivated', 0)
                // 2 + 3 + 1 = 6; the trashed Active company is excluded.
                ->where('totalCompanies', 6));
    }

    // ---------------------------------------------------------------------
    // Companies list — search, status filter, soft-delete exclusion (Req D1–D4)
    // ---------------------------------------------------------------------

    public function test_index_renders_paginated_companies_and_reflects_empty_filters(): void
    {
        $admin = $this->platformAdmin();

        Company::factory()->count(3)->create(['status' => CompanyStatus::Active]);

        $this->actingAs($admin, 'platform')
            ->get(route('platform.companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Companies/Index')
                ->has('companies.data', 3)
                ->has('companies.links')
                ->where('companies.per_page', 15)
                ->where('filters.search', null)
                ->where('filters.status', null));
    }

    public function test_index_search_matches_name_and_slug(): void
    {
        $admin = $this->platformAdmin();

        $byName = Company::factory()->create(['name' => 'Acme Rockets', 'slug' => 'acme-rockets']);
        $bySlug = Company::factory()->create(['name' => 'Totally Different', 'slug' => 'zephyr-labs']);
        Company::factory()->create(['name' => 'Globex Industries', 'slug' => 'globex']);

        // Matches on name.
        $this->actingAs($admin, 'platform')
            ->get(route('platform.companies.index', ['search' => 'Acme']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.id', $byName->id)
                ->where('filters.search', 'Acme'));

        // Matches on slug.
        $this->actingAs($admin, 'platform')
            ->get(route('platform.companies.index', ['search' => 'zephyr']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.id', $bySlug->id));
    }

    public function test_index_status_filter_narrows_results(): void
    {
        $admin = $this->platformAdmin();

        Company::factory()->count(2)->create(['status' => CompanyStatus::Active]);
        $pending = Company::factory()->create(['status' => CompanyStatus::Pending]);

        $this->actingAs($admin, 'platform')
            ->get(route('platform.companies.index', ['status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.id', $pending->id)
                ->where('filters.status', 'pending'));
    }

    public function test_index_excludes_soft_deleted_companies(): void
    {
        $admin = $this->platformAdmin();

        Company::factory()->count(2)->create(['status' => CompanyStatus::Active]);
        Company::factory()->create(['status' => CompanyStatus::Active])->delete();

        $this->actingAs($admin, 'platform')
            ->get(route('platform.companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 2)
                ->where('companies.total', 2));
    }

    // ---------------------------------------------------------------------
    // Create — form page + pending create, NO provisioning (Req E1–E4)
    // ---------------------------------------------------------------------

    public function test_create_renders_the_form_page(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin, 'platform')
            ->get(route('platform.companies.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Platform/Companies/Create'));
    }

    public function test_store_creates_a_pending_company_without_provisioning_and_redirects_with_success(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin, 'platform')->post(route('platform.companies.store'), [
            'name' => 'New Horizons',
            'slug' => 'new-horizons',
            'email' => 'ops@new-horizons.test',
            'country_code' => 'IN',
        ]);

        $company = Company::query()->where('slug', 'new-horizons')->firstOrFail();

        $response->assertRedirect(route('platform.companies.show', $company));
        $response->assertSessionHas('success');

        // Created pending: no provisioning kicked off.
        $this->assertSame(CompanyStatus::Pending, $company->status);

        // database_name is assigned by CompanyService, but status stays pending (no workspace DB).
        $this->assertNotNull($company->database_name);
        $this->assertSame('workspace_'.strtolower($company->id), $company->database_name);
        $this->assertFalse(
            $this->workspaceDatabaseExists($company->database_name),
            'Creating a company must not provision a physical workspace database.'
        );
    }

    public function test_store_with_invalid_input_returns_field_errors_and_creates_nothing(): void
    {
        $admin = $this->platformAdmin();

        // Missing name and a reserved slug both surface as field-keyed validation errors.
        $response = $this->actingAs($admin, 'platform')
            ->from(route('platform.companies.create'))
            ->post(route('platform.companies.store'), [
                'name' => '',
                'slug' => 'www',
            ]);

        $response->assertRedirect(route('platform.companies.create'));
        $response->assertSessionHasErrors(['name', 'slug']);

        $this->assertSame(0, Company::query()->count());
    }

    public function test_store_rejects_a_duplicate_slug(): void
    {
        $admin = $this->platformAdmin();
        Company::factory()->create(['slug' => 'taken-slug']);

        $response = $this->actingAs($admin, 'platform')
            ->from(route('platform.companies.create'))
            ->post(route('platform.companies.store'), [
                'name' => 'Another Co',
                'slug' => 'taken-slug',
            ]);

        $response->assertSessionHasErrors('slug');
        $this->assertSame(1, Company::query()->count());
    }

    // ---------------------------------------------------------------------
    // Show — company + effective domain + per-company can (Req F1, B3)
    // ---------------------------------------------------------------------

    public function test_show_returns_company_with_null_effective_domain_and_admin_can_map(): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create(['status' => CompanyStatus::Pending]);

        $this->actingAs($admin, 'platform')
            ->get(route('platform.companies.show', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Companies/Show')
                ->where('company.id', $company->id)
                ->where('effectiveDomain', null)
                ->where('can.view', true)
                ->where('can.provision', true)
                ->where('can.manageDomains', true));
    }

    public function test_show_returns_the_primary_domain_as_the_effective_domain(): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);

        Domain::factory()->create([
            'company_id' => $company->id,
            'domain' => 'secondary.example.test',
            'is_primary' => false,
        ]);
        $primary = Domain::factory()->primary()->create([
            'company_id' => $company->id,
            'domain' => 'primary.example.test',
            'status' => DomainStatus::Active,
        ]);

        $this->actingAs($admin, 'platform')
            ->get(route('platform.companies.show', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('effectiveDomain.id', $primary->id)
                ->where('effectiveDomain.domain', 'primary.example.test')
                ->where('effectiveDomain.is_primary', true));
    }

    // ---------------------------------------------------------------------
    // Provision / retry — sync delegate + status reflect (Req F2, F3, 1E-E)
    // ---------------------------------------------------------------------

    public function test_provision_transitions_a_pending_company_to_active_with_success_flash(): void
    {
        $admin = $this->platformAdmin();

        $company = Company::factory()->create(['status' => CompanyStatus::Pending]);
        // Clean up whatever the real provisioning path creates for this company.
        $this->workspaceDatabases->trackExisting($company->database_name);

        $response = $this->actingAs($admin, 'platform')
            ->post(route('platform.companies.provision', $company));

        $response->assertRedirect(route('platform.companies.show', $company));
        $response->assertSessionHas('success');

        $this->assertSame(CompanyStatus::Active, $company->fresh()->status);
    }

    public function test_provision_of_an_active_company_flashes_error_without_500_and_leaves_status_unchanged(): void
    {
        $admin = $this->platformAdmin();

        // An already-active company drives the CompanyNotProvisionableException path.
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);

        $response = $this->actingAs($admin, 'platform')
            ->post(route('platform.companies.provision', $company));

        $response->assertRedirect(route('platform.companies.show', $company));
        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');

        $this->assertSame(CompanyStatus::Active, $company->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Domains — add / change the effective domain delegate (Req F4, F5, 1E-C)
    // ---------------------------------------------------------------------

    public function test_store_adds_a_primary_domain_for_a_company_with_none(): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);

        $response = $this->actingAs($admin, 'platform')
            ->post(route('platform.companies.domain.store', $company), [
                'domain' => 'acme.example.test',
                'type' => DomainType::Custom->value,
            ]);

        $response->assertRedirect(route('platform.companies.show', $company));
        $response->assertSessionHas('success');

        $domain = Domain::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('acme.example.test', $domain->domain);
        $this->assertTrue($domain->is_primary);
    }

    public function test_update_changes_the_effective_domain_demoting_the_old_primary(): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);

        $old = Domain::factory()->primary()->create([
            'company_id' => $company->id,
            'domain' => 'old.example.test',
        ]);

        $response = $this->actingAs($admin, 'platform')
            ->put(route('platform.companies.domain.update', $company), [
                'domain' => 'new.example.test',
                'type' => DomainType::Custom->value,
            ]);

        $response->assertRedirect(route('platform.companies.show', $company));
        $response->assertSessionHas('success');

        $new = Domain::query()
            ->where('company_id', $company->id)
            ->where('domain', 'new.example.test')
            ->firstOrFail();

        $this->assertTrue($new->is_primary, 'The replacement domain must become primary.');
        $this->assertFalse($old->fresh()->is_primary, 'The old domain must be demoted.');
    }

    public function test_domain_store_with_invalid_input_surfaces_a_domain_field_error(): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);

        // Missing domain fails boundary validation keyed to the `domain` field.
        $response = $this->actingAs($admin, 'platform')
            ->from(route('platform.companies.show', $company))
            ->post(route('platform.companies.domain.store', $company), [
                'domain' => '',
                'type' => DomainType::Custom->value,
            ]);

        $response->assertSessionHasErrors('domain');
        $this->assertSame(0, Domain::query()->where('company_id', $company->id)->count());
    }

    public function test_domain_store_with_a_malformed_hostname_surfaces_a_domain_field_error(): void
    {
        $admin = $this->platformAdmin();
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);

        // A hostname with a protocol is rejected by the Hostname value object inside the Action
        // and surfaces as a validation error keyed to `domain`.
        $response = $this->actingAs($admin, 'platform')
            ->from(route('platform.companies.show', $company))
            ->post(route('platform.companies.domain.store', $company), [
                'domain' => 'https://bad.example.test',
                'type' => DomainType::Custom->value,
            ]);

        $response->assertSessionHasErrors('domain');
        $this->assertSame(0, Domain::query()->where('company_id', $company->id)->count());
    }

    /**
     * Does a physical workspace database exist? Checked on the central platform connection.
     */
    private function workspaceDatabaseExists(string $databaseName): bool
    {
        $found = DB::connection('platform')->select(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$databaseName]
        );

        return $found !== [];
    }
}
