<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace (tenant) migration — workspace role ↔ permission pivot (1G).
 *
 * Runs against the dynamic `tenant` connection (no explicit connection). Mirrors the Platform
 * `platform_role_permissions` pivot: composite unique (role_id, permission_id) and NO ACTION
 * foreign keys. References workspace_roles + workspace_permissions (both created earlier in
 * the 2026_03_21_* sequence).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_role_permissions', function (Blueprint $table) {
            $table->ulid('role_id');
            $table->ulid('permission_id');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);

            $table->foreign('role_id')
                ->references('id')
                ->on('workspace_roles')
                ->noActionOnDelete();

            $table->foreign('permission_id')
                ->references('id')
                ->on('workspace_permissions')
                ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_role_permissions');
    }
};
