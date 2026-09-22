<?php

namespace Database\Factories;

use App\Models\PlatformPermission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformPermission>
 */
class PlatformPermissionFactory extends Factory
{
    protected $model = PlatformPermission::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'status' => 1,
        ];
    }
}
