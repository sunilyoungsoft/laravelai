<?php

namespace Tests\Feature\Workspace;

use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * WorkspaceUser lives in — and is isolated to — a single Company's tenant database (1F-B).
 *
 * Confirms the model persists inside tenant context, hashes passwords via Argon2id
 * (the `hashed` cast), and that a user created in Company A's workspace is invisible
 * when Company B's workspace is active (tenant isolation).
 */
class WorkspaceUserModelTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    public function test_workspace_user_persists_inside_tenant_context_with_argon2id_password(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $user = WorkspaceUser::create([
                'name' => 'Workspace Admin',
                'email' => 'admin@acme.test',
                'password' => 'temp-password',
            ]);

            $stored = WorkspaceUser::query()->whereKey($user->id)->firstOrFail();

            $this->assertSame('admin@acme.test', $stored->email);
            $this->assertNotSame('temp-password', $stored->password);
            $this->assertSame('argon2id', password_get_info($stored->password)['algoName']);
            $this->assertTrue(Hash::check('temp-password', $stored->password));
        });
    }

    public function test_workspace_user_in_company_a_is_invisible_from_company_b(): void
    {
        $companyA = $this->provisionCompany('workspace-a');
        $companyB = $this->provisionCompany('workspace-b');

        $companyA->run(function () {
            WorkspaceUser::create([
                'name' => 'A Admin',
                'email' => 'shared@example.test',
                'password' => 'secret',
            ]);
        });

        // The same email can exist independently in B, and A's row is not visible here.
        $companyB->run(function () {
            $this->assertSame(0, WorkspaceUser::query()->count());
            $this->assertFalse(
                WorkspaceUser::query()->where('email', 'shared@example.test')->exists()
            );

            WorkspaceUser::create([
                'name' => 'B Admin',
                'email' => 'shared@example.test',
                'password' => 'secret',
            ]);

            $this->assertSame(1, WorkspaceUser::query()->count());
        });

        // A still sees only its own user.
        $companyA->run(function () {
            $this->assertSame(1, WorkspaceUser::query()->count());
            $this->assertSame('A Admin', WorkspaceUser::query()->firstOrFail()->name);
        });
    }

    public function test_workspace_users_table_is_absent_from_the_platform_database(): void
    {
        $this->provisionCompany();

        $this->assertFalse(
            DB::connection('platform')->getSchemaBuilder()->hasTable('workspace_users')
        );
    }
}
