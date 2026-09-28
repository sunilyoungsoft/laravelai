<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformRole;
use App\Models\PlatformUser;
use App\Services\Platform\CreatePlatformAdminService;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class CreatePlatformAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_first_admin_user_with_hashed_password(): void
    {
        $this->seed(PlatformRoleSeeder::class);

        $user = app(CreatePlatformAdminService::class)->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'phone' => '9811111111',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);

        $this->assertDatabaseHas('platform_users', [
            'email' => 'platform-admin@example.com',
            'phone' => '9811111111',
            'status' => 1,
        ], 'platform');

        $rawPassword = DB::connection('platform')
            ->table('platform_users')
            ->where('id', $user->id)
            ->value('password');

        $this->assertNotSame('SecretPass1!', $rawPassword);
        $this->assertTrue(Hash::check('SecretPass1!', $rawPassword));
        $this->assertTrue($user->roles()->where('slug', 'admin')->exists());
        $this->assertNull($user->created_by);
    }

    public function test_second_bootstrap_admin_is_rejected(): void
    {
        $this->seed(PlatformRoleSeeder::class);

        app(CreatePlatformAdminService::class)->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'phone' => '9811111111',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A Platform Admin already exists');

        app(CreatePlatformAdminService::class)->create([
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'phone' => '9822222222',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);
    }

    public function test_soft_deleted_existing_admin_prevents_replacement_bootstrap(): void
    {
        $this->seed(PlatformRoleSeeder::class);

        $admin = app(CreatePlatformAdminService::class)->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'phone' => '9811111111',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);

        $admin->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('soft-deleted');

        app(CreatePlatformAdminService::class)->create([
            'name' => 'Replacement Admin',
            'email' => 'replacement@example.com',
            'phone' => '9833333333',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);
    }

    public function test_soft_deleted_admin_role_gives_recovery_message(): void
    {
        $this->seed(PlatformRoleSeeder::class);

        PlatformRole::on('platform')->where('slug', 'admin')->firstOrFail()->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Admin system role is soft-deleted');

        app(CreatePlatformAdminService::class)->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'phone' => '9811111111',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);
    }

    public function test_missing_admin_role_gives_clear_message(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Admin system role does not exist');

        app(CreatePlatformAdminService::class)->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'phone' => '9811111111',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);
    }

    public function test_it_rejects_duplicate_email(): void
    {
        $this->seed(PlatformRoleSeeder::class);

        PlatformUser::factory()->create([
            'email' => 'platform-admin@example.com',
        ]);

        $this->expectException(ValidationException::class);

        app(CreatePlatformAdminService::class)->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'phone' => '9822222222',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);
    }

    public function test_it_rejects_duplicate_phone(): void
    {
        $this->seed(PlatformRoleSeeder::class);

        PlatformUser::factory()->create([
            'phone' => '9811111111',
        ]);

        $this->expectException(ValidationException::class);

        app(CreatePlatformAdminService::class)->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'phone' => '9811111111',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);
    }

    public function test_platform_create_admin_command_happy_path(): void
    {
        $this->seed(PlatformRoleSeeder::class);

        $this->artisan('platform:create-admin')
            ->expectsQuestion('Name', 'CLI Admin')
            ->expectsQuestion('Email', 'cli-admin@example.com')
            ->expectsQuestion('Phone', '9844444444')
            ->expectsQuestion('Password', 'SecretPass1!')
            ->expectsQuestion('Confirm password', 'SecretPass1!')
            ->assertSuccessful();

        $this->assertDatabaseHas('platform_users', [
            'email' => 'cli-admin@example.com',
            'phone' => '9844444444',
        ], 'platform');

        $user = PlatformUser::on('platform')->where('email', 'cli-admin@example.com')->firstOrFail();
        $this->assertTrue($user->roles()->where('slug', 'admin')->exists());
    }

    public function test_platform_create_admin_command_rejects_second_bootstrap(): void
    {
        $this->seed(PlatformRoleSeeder::class);

        app(CreatePlatformAdminService::class)->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'phone' => '9811111111',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);

        $this->artisan('platform:create-admin')
            ->expectsQuestion('Name', 'Second Admin')
            ->expectsQuestion('Email', 'second@example.com')
            ->expectsQuestion('Phone', '9855555555')
            ->expectsQuestion('Password', 'SecretPass1!')
            ->expectsQuestion('Confirm password', 'SecretPass1!')
            ->expectsOutputToContain('A Platform Admin already exists')
            ->assertFailed();
    }

    public function test_sensitive_platform_user_fields_are_not_mass_assignable(): void
    {
        $user = PlatformUser::factory()->create();

        $user->fill([
            'status' => 0,
            'is_locked' => true,
            'two_factor_enabled' => true,
            'two_factor_secret' => 'should-not-assign',
            'failed_login_attempts' => 9,
        ]);
        $user->save();

        $user->refresh();

        $this->assertSame(1, $user->status);
        $this->assertFalse($user->is_locked);
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->two_factor_secret);
        $this->assertSame(0, $user->failed_login_attempts);
    }

    public function test_is_system_is_not_mass_assignable_on_platform_role(): void
    {
        $role = PlatformRole::factory()->create(['is_system' => false]);

        $role->fill([
            'is_system' => true,
            'status' => 0,
            'name' => 'Updated',
        ]);
        $role->save();

        $role->refresh();

        $this->assertFalse($role->is_system);
        $this->assertSame(1, $role->status);
        $this->assertSame('Updated', $role->name);
    }
}
