<?php

namespace Database\Seeders;

use App\Models\PlatformRole;
use Illuminate\Database\Seeder;

class PlatformRoleSeeder extends Seeder
{
    /**
     * Seed the protected Admin system role.
     *
     * Soft-delete safe: searches withTrashed, restores if needed, then enforces
     * Admin field invariants. Admin does not use wildcard permissions — future
     * Platform permissions must be assigned to this role explicitly.
     */
    public function run(): void
    {
        $role = PlatformRole::on('platform')
            ->withTrashed()
            ->where('slug', 'admin')
            ->first();

        if ($role === null) {
            PlatformRole::on('platform')->create([
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Protected platform system administrator role.',
                'is_system' => true,
                'status' => 1,
            ]);

            return;
        }

        if ($role->trashed()) {
            $role->restore();
        }

        $role->forceFill([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'status' => 1,
        ])->save();
    }
}
