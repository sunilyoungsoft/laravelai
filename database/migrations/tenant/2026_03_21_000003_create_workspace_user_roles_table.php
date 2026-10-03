<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace (tenant) migration — workspace user ↔ role pivot (1G).
 *
 * Runs against the dynamic `tenant` connection (no explicit connection). Mirrors the Platform
 * `platform_user_roles` pivot: composite unique (user_id, role_id) and NO ACTION foreign keys
 * (noActionOnDelete), per the project's FK/delete conventions. References workspace_users
 * (created in 2026_03_20_000001) and workspace_roles (created in 2026_03_21_000001), both of
 * which this migration sorts after.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_user_roles', function (Blueprint $table) {
            $table->ulid('user_id');
            $table->ulid('role_id');
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('workspace_users')
                ->noActionOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('workspace_roles')
                ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user_roles');
    }
};
