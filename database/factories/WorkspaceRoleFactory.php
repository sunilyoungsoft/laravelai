<?php

namespace Database\Factories;

use App\Models\WorkspaceRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkspaceRole>
 *
 * WorkspaceRole resolves on the dynamic `tenant` connection, so this factory must be used
 * inside tenant context (e.g. within $company->run(...)).
 */
class WorkspaceRoleFactory extends Factory
{
    protected $model = WorkspaceRole::class;

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

    /**
     * The protected Workspace Admin system role.
     */
    public function admin(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Workspace Admin',
            'slug' => 'workspace-admin',
            'is_system' => true,
            'status' => 1,
        ]);
    }
}
