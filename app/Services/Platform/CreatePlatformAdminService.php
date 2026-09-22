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
     * Create the first Platform Admin user and assign the Admin system role.
     *
     * Password hashing is handled by PlatformUser's `hashed` cast — do not
     * hash the password in this service.
     *
     * @param  array{name: string, email: string, phone: string, password: string}  $attributes
     */
    public function create(array $attributes): PlatformUser
    {
        $validated = $this->validate($attributes);

        $adminRole = PlatformRole::on('platform')
            ->where('slug', 'admin')
            ->where('is_system', true)
            ->first();

        if ($adminRole === null) {
            throw new RuntimeException(
                'The Admin system role is missing. Run: php artisan db:seed --class=PlatformRoleSeeder --database=platform'
            );
        }

        return DB::connection('platform')->transaction(function () use ($validated, $adminRole) {
            $user = new PlatformUser;
            $user->setConnection('platform');
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'status' => 1,
                'created_by' => null,
                'updated_by' => null,
            ]);
            $user->save();

            $user->roles()->attach($adminRole->id);

            return $user->fresh(['roles']);
        });
    }

    /**
     * @param  array{name: string, email: string, phone: string, password: string}  $attributes
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
