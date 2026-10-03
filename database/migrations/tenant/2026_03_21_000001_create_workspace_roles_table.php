<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace (tenant) migration — per-company roles (1G).
 *
 * Runs against the dynamic `tenant` connection while tenancy is initialized (via
 * WorkspaceDatabaseService::runWorkspaceMigrations inside Stancl tenant context). It MUST
 * NOT set an explicit connection — it targets the tenant database, so every Company's
 * workspace gets its own isolated roles. Workspace authorization data is tenant data and
 * must never live in the Platform database.
 *
 * Mirrors the Platform `platform_roles` conventions: ULID pk, globally-unique slug (reserved
 * across soft-deletes), an `is_system` flag for protected roles (e.g. workspace-admin), and a
 * status column. The admin system role is authorized via a Gate::before short-circuit; normal
 * roles carry explicit permissions (no wildcards).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_roles');
    }
};
