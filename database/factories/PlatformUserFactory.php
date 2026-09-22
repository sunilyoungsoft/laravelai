<?php

namespace Database\Factories;

use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformUser>
 */
class PlatformUserFactory extends Factory
{
    protected $model = PlatformUser::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('98########'),
            'password' => 'password',
            'status' => 1,
            'failed_login_attempts' => 0,
            'is_locked' => false,
            'two_factor_enabled' => false,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
