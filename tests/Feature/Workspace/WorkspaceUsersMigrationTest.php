<?php

namespace Tests\Feature\Workspace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * The workspace_users table is a tenant (workspace) migration (1F-B).
 *
 * It must be created in each Company's own workspace database when the workspace
 * is provisioned, and must never exist in the central Platform database — workspace
 * users are tenant business data.
 */
class WorkspaceUsersMigrationTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    public function test_provisioning_creates_workspace_users_table_in_the_tenant_database(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $this->assertTrue(
                Schema::connection('tenant')->hasTable('workspace_users'),
                'workspace_users must exist in the tenant database after provisioning.'
            );

            $columns = Schema::connection('tenant')->getColumnListing('workspace_users');

            foreach (['id', 'name', 'email', 'password', 'must_change_password', 'status', 'last_login_at', 'deleted_at'] as $column) {
                $this->assertContains($column, $columns, "workspace_users must have a [{$column}] column.");
            }
        });
    }

    public function test_workspace_users_table_is_not_created_in_the_platform_database(): void
    {
        $this->provisionCompany();

        $this->assertFalse(
            Schema::connection('platform')->hasTable('workspace_users'),
            'workspace_users must never exist in the Platform database.'
        );
    }
}
