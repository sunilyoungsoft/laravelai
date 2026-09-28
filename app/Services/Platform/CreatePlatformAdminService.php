<?php

namespace App\Services\Platform;

use App\Models\PlatformRole;
use App\Models\PlatformUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreatePlatformAdminService
{
    /**
     * Bootstrap the FIRST Platform Admin only.
     *
     * Password hashing is handled by PlatformUser's `hashed` cast.
     *
     * @param  array{name: string, email: string, phone: string, password: string, password_confirmation?: string}  $attributes
     */
    public function create(array $attributes): PlatformUser
    {
        $this->ensureAdminRoleIsActive();
        $this->ensureNoExistingAdminUser();

        $validated = $this->validate($attributes);

        $adminRole = PlatformRole::on('platform')
            ->where('slug', 'admin')
            ->where('is_system', true)
            ->firstOrFail();

        return DB::connection('platform')->transaction(function () use ($validated, $adminRole) {
            $user = new PlatformUser;
            $user->setConnection('platform');
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
            ]);
            $user->status = 1;
            $user->created_by = null;
            $user->updated_by = null;
            $user->save();

            $user->roles()->attach($adminRole->id);

            return $user->fresh(['roles']);
        });
    }

    private function ensureAdminRoleIsActive(): void
    {
        $adminRole = PlatformRole::on('platform')
            ->withTrashed()
            ->where('slug', 'admin')
            ->where('is_system', true)
            ->first();

        if ($adminRole === null) {
            throw new RuntimeException(
                'The Admin system role does not exist. Run: php artisan db:seed --class=PlatformRoleSeeder --database=platform'
            );
        }

        if ($adminRole->trashed()) {
            throw new RuntimeException(
                'The Admin system role is soft-deleted. Restore it by running: php artisan db:seed --class=PlatformRoleSeeder --database=platform'
            );
        }
    }

    private function ensureNoExistingAdminUser(): void
    {
        $existingAdmin = PlatformUser::on('platform')
            ->withTrashed()
            ->whereHas('roles', function ($query): void {
                $query->where('slug', 'admin')->where('is_system', true);
            })
            ->first();

        if ($existingAdmin === null) {
            return;
        }

        if ($existingAdmin->trashed()) {
            throw new RuntimeException(
                "A Platform Admin already exists but is soft-deleted (email: {$existingAdmin->email}). ".
                'Restore that account instead of creating a replacement. '.
                'platform:create-admin is bootstrap-only for the first Admin.'
            );
        }

        throw new RuntimeException(
            "A Platform Admin already exists (email: {$existingAdmin->email}). ".
            'platform:create-admin is bootstrap-only. '.
            'Create additional Platform Admins through Platform administration later.'
        );
    }

    /**
     * @param  array{name: string, email: string, phone: string, password: string, password_confirmation?: string}  $attributes
     * @return array{name: string, email: string, phone: string, password: string}
     */
    private function validate(array $attributes): array
    {
        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:platform.platform_users,email'],
            'phone' => ['required', 'string', 'max:255', 'unique:platform.platform_users,phone'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{name: string, email: string, phone: string, password: string} $validated */
        $validated = $validator->validated();

        return $validated;
    }
}
