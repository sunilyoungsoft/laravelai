<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace (tenant) migration — per-company permissions (1G).
 *
 * Runs against the dynamic `tenant` connection (no explicit connection). Mirrors the Platform
 * `platform_permissions` conventions. Permissions use a `{module}.{action}` slug convention
 * (e.g. workspace.access); Phase 1G seeds only the proof permissions and assigns them to the
 * workspace-admin system role explicitly. No wildcard permission records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_permissions');
    }
};
