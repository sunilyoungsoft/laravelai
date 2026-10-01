<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace (tenant) migration.
 *
 * Runs against the dynamic `tenant` connection while tenancy is initialized (via
 * WorkspaceDatabaseService::runWorkspaceMigrations, which uses Stancl's tenant context).
 * It must NOT set an explicit connection — it targets whatever the default connection is
 * inside tenant context, never the `platform` database.
 *
 * `workspace_meta` is a minimal, non-business table proving that per-Workspace migrations
 * run and can be verified. Real ERP/business tables arrive in later module phases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_meta', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_meta');
    }
};
