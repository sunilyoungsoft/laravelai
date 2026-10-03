<?php

namespace Database\Factories;

use App\Models\WorkspaceUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceUser>
 *
 * WorkspaceUser resolves on the dynamic `tenant` connection, so this factory must be
 * used inside tenant context (e.g. within $company->run(...) or after
 * tenancy()->initialize($company)). Outside tenant context there is no workspace
 * database selected and persistence will fail — by design.
 */
class WorkspaceUserFactory extends Factory
{
    protected $model = WorkspaceUser::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'must_change_password' => false,
            'status' => 1,
        ];
    }

    /**
     * A workspace user that must change their password on first login (the state of
     * an initial Workspace Admin created with a temporary password).
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (): array => ['must_change_password' => true]);
    }
}
