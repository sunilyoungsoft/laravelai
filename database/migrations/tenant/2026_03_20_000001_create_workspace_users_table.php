<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace (tenant) migration — the per-company user table (1F-B).
 *
 * Runs against the dynamic `tenant` connection while tenancy is initialized (via
 * WorkspaceDatabaseService::runWorkspaceMigrations inside Stancl tenant context).
 * It MUST NOT set an explicit connection — it targets whatever the default connection
 * is inside tenant context, so every Company's workspace database gets its own
 * isolated `workspace_users` table. Workspace users are tenant business data and must
 * never live in the Platform database.
 *
 * `email` is unique only within a single tenant database (the same email may exist in
 * different companies, since each has its own database). Passwords are hashed via
 * Laravel's Hash abstraction (Argon2id); `must_change_password` drives the forced
 * first-login change for the initial Workspace Admin (1F-F).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(true);
            $table->tinyInteger('status')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_users');
    }
};
