<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application-owned Platform `domains` table.
 *
 * Maps a hostname to a Company on the platform database. This is NOT Stancl's stock domains
 * table (that is never published/migrated). Hostname → Company resolution reads this table on
 * the platform connection before any tenant context is initialized.
 */
return new class extends Migration
{
    protected $connection = 'platform';

    public function up(): void
    {
        Schema::connection($this->connection)->create('domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id');

            // Normalized hostname (lowercased, trimmed, no trailing dot). Max hostname length 253.
            $table->string('domain', 253);

            $table->string('type');
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();

            $table->ulid('created_by');
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Plain unique index: reserves the hostname identity even for soft-deleted rows,
            // consistent with the platform soft-delete identity policy.
            $table->unique('domain');

            $table->index('company_id');
            $table->index('status');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->noActionOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('platform_users')
                ->noActionOnDelete();

            $table->foreign('updated_by')
                ->references('id')
                ->on('platform_users')
                ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('domains');
    }
};
