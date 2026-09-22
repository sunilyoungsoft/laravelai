<?php

namespace Database\Factories;

use App\Models\PlatformRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformRole>
 */
class PlatformRoleFactory extends Factory
{
    protected $model = PlatformRole::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'is_system' => false,
            'status' => 1,
        ];
    }
}
