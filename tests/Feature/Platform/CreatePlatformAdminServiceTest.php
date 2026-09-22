<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformUser;
use App\Services\Platform\CreatePlatformAdminService;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreatePlatformAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_admin_user_and_assigns_admin_role_using_hashed_cast(): void
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

        $this->assertTrue(Hash::check('SecretPass1!', $user->password));
        $this->assertTrue($user->roles()->where('slug', 'admin')->exists());
        $this->assertNull($user->created_by);
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

    public function test_it_fails_when_admin_role_is_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Admin system role is missing');

        app(CreatePlatformAdminService::class)->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'phone' => '9833333333',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ]);
    }
}
