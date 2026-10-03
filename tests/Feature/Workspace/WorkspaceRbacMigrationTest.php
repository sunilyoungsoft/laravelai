<?php

namespace Tests\Feature\Workspace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * The Workspace RBAC tables are tenant (workspace) migrations (1G).
 *
 * They must be created in each Company's own workspace database when provisioned, and must
 * never exist in the central Platform database — workspace authorization is tenant data.
 */
class WorkspaceRbacMigrationTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    /** @var list<string> */
    private array $rbacTables = [
        'workspace_roles',
        'workspace_permissions',
        'workspace_user_roles',
        'workspace_role_permissions',
    ];

    public function test_provisioning_creates_the_rbac_tables_in_the_tenant_database(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            foreach ($this->rbacTables as $table) {
                $this->assertTrue(
                    Schema::connection('tenant')->hasTable($table),
                    "{$table} must exist in the tenant database after provisioning."
                );
            }
        });
    }

    public function test_rbac_tables_are_not_created_in_the_platform_database(): void
    {
        $this->provisionCompany();

        foreach ($this->rbacTables as $table) {
            $this->assertFalse(
                Schema::connection('platform')->hasTable($table),
                "{$table} must never exist in the Platform database."
            );
        }
    }

    public function test_role_and_permission_tables_have_the_expected_columns(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $roleColumns = Schema::connection('tenant')->getColumnListing('workspace_roles');
            foreach (['id', 'name', 'slug', 'description', 'is_system', 'status', 'deleted_at'] as $column) {
                $this->assertContains($column, $roleColumns, "workspace_roles must have [{$column}].");
            }

            $permissionColumns = Schema::connection('tenant')->getColumnListing('workspace_permissions');
            foreach (['id', 'name', 'slug', 'description', 'status', 'deleted_at'] as $column) {
                $this->assertContains($column, $permissionColumns, "workspace_permissions must have [{$column}].");
            }
        });
    }
}
