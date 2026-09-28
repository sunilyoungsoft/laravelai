<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Models\PlatformUser;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_migrations_create_rbac_tables(): void
    {
        $this->assertTrue(Schema::connection('platform')->hasTable('platform_users'));
        $this->assertTrue(Schema::connection('platform')->hasTable('platform_roles'));
        $this->assertTrue(Schema::connection('platform')->hasTable('platform_permissions'));
        $this->assertTrue(Schema::connection('platform')->hasTable('platform_user_roles'));
        $this->assertTrue(Schema::connection('platform')->hasTable('platform_role_permissions'));
    }

    public function test_admin_role_seeder_is_idempotent_including_soft_deleted_admin(): void
    {
        $this->seed(PlatformRoleSeeder::class);
        $this->seed(PlatformRoleSeeder::class);

        $this->assertSame(1, PlatformRole::on('platform')->withTrashed()->where('slug', 'admin')->count());

        $admin = PlatformRole::on('platform')->where('slug', 'admin')->firstOrFail();
        $admin->delete();

        $this->seed(PlatformRoleSeeder::class);

        $restored = PlatformRole::on('platform')->where('slug', 'admin')->firstOrFail();

        $this->assertFalse($restored->trashed());
        $this->assertSame('Admin', $restored->name);
        $this->assertSame('admin', $restored->slug);
        $this->assertTrue($restored->is_system);
        $this->assertSame(1, $restored->status);
        $this->assertTrue($restored->isAdminSystemRole());
        $this->assertSame(1, PlatformRole::on('platform')->withTrashed()->where('slug', 'admin')->count());
    }

    public function test_platform_user_can_have_roles(): void
    {
        $user = PlatformUser::factory()->create();
        $role = PlatformRole::factory()->create();

        $user->roles()->attach($role->id);

        $this->assertTrue($user->roles()->where('platform_roles.id', $role->id)->exists());
    }

    public function test_platform_role_can_have_permissions(): void
    {
        $role = PlatformRole::factory()->create();
        $permission = PlatformPermission::factory()->create();

        $role->permissions()->attach($permission->id);

        $this->assertTrue($role->permissions()->where('platform_permissions.id', $permission->id)->exists());
    }

    public function test_duplicate_user_role_assignments_are_rejected(): void
    {
        $user = PlatformUser::factory()->create();
        $role = PlatformRole::factory()->create();

        $user->roles()->attach($role->id);

        $this->expectException(QueryException::class);
        $user->roles()->attach($role->id);
    }

    public function test_duplicate_role_permission_assignments_are_rejected(): void
    {
        $role = PlatformRole::factory()->create();
        $permission = PlatformPermission::factory()->create();

        $role->permissions()->attach($permission->id);

        $this->expectException(QueryException::class);
        $role->permissions()->attach($permission->id);
    }

    public function test_duplicate_role_slugs_are_rejected(): void
    {
        PlatformRole::factory()->create(['slug' => 'support']);

        $this->expectException(QueryException::class);
        PlatformRole::factory()->create(['slug' => 'support']);
    }

    public function test_duplicate_permission_slugs_are_rejected(): void
    {
        PlatformPermission::factory()->create(['slug' => 'companies.view']);

        $this->expectException(QueryException::class);
        PlatformPermission::factory()->create(['slug' => 'companies.view']);
    }

    public function test_duplicate_user_emails_are_rejected(): void
    {
        PlatformUser::factory()->create(['email' => 'admin@example.com']);

        $this->expectException(QueryException::class);
        PlatformUser::factory()->create(['email' => 'admin@example.com']);
    }

    public function test_duplicate_user_phones_are_rejected(): void
    {
        PlatformUser::factory()->create(['phone' => '9800000001']);

        $this->expectException(QueryException::class);
        PlatformUser::factory()->create(['phone' => '9800000001']);
    }

    public function test_soft_deleted_user_email_remains_reserved(): void
    {
        $user = PlatformUser::factory()->create(['email' => 'reserved@example.com']);
        $user->delete();

        $this->expectException(QueryException::class);
        PlatformUser::factory()->create(['email' => 'reserved@example.com']);
    }

    public function test_soft_deleted_user_phone_remains_reserved(): void
    {
        $user = PlatformUser::factory()->create(['phone' => '9800000099']);
        $user->delete();

        $this->expectException(QueryException::class);
        PlatformUser::factory()->create(['phone' => '9800000099']);
    }

    public function test_soft_deleted_role_slug_remains_reserved(): void
    {
        $role = PlatformRole::factory()->create(['slug' => 'support']);
        $role->delete();

        $this->expectException(QueryException::class);
        PlatformRole::factory()->create(['slug' => 'support']);
    }

    public function test_soft_deleted_permission_slug_remains_reserved(): void
    {
        $permission = PlatformPermission::factory()->create(['slug' => 'companies.view']);
        $permission->delete();

        $this->expectException(QueryException::class);
        PlatformPermission::factory()->create(['slug' => 'companies.view']);
    }

    public function test_models_use_the_platform_connection(): void
    {
        $this->assertSame('platform', (new PlatformUser)->getConnectionName());
        $this->assertSame('platform', (new PlatformRole)->getConnectionName());
        $this->assertSame('platform', (new PlatformPermission)->getConnectionName());
    }

    public function test_ulids_are_generated_for_platform_models(): void
    {
        $user = PlatformUser::factory()->create();
        $role = PlatformRole::factory()->create();
        $permission = PlatformPermission::factory()->create();

        foreach ([$user->id, $role->id, $permission->id] as $id) {
            $this->assertIsString($id);
            $this->assertSame(26, strlen($id));
        }
    }

    public function test_two_factor_secret_is_stored_encrypted(): void
    {
        $user = PlatformUser::factory()->create();
        $user->two_factor_secret = 'plain-secret-value';
        $user->save();

        $raw = DB::connection('platform')
            ->table('platform_users')
            ->where('id', $user->id)
            ->value('two_factor_secret');

        $this->assertNotNull($raw);
        $this->assertNotSame('plain-secret-value', $raw);
        $this->assertSame('plain-secret-value', $user->fresh()->two_factor_secret);
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
    }
}
