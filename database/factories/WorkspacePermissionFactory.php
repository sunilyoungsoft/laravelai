<?php

namespace Database\Factories;

use App\Models\WorkspacePermission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkspacePermission>
 *
 * WorkspacePermission resolves on the dynamic `tenant` connection, so this factory must be
 * used inside tenant context (e.g. within $company->run(...)).
 */
class WorkspacePermissionFactory extends Factory
{
    protected $model = WorkspacePermission::class;

    public function definition(): array
    {
        $module = fake()->unique()->word();
        $action = fake()->randomElement(['view', 'create', 'update', 'delete']);

        return [
            'name' => Str::title($module).' '.Str::title($action),
            'slug' => $module.'.'.$action,
            'description' => fake()->optional()->sentence(),
            'status' => 1,
        ];
    }
}
