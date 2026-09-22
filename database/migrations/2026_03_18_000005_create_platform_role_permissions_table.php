<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'platform';

    public function up(): void
    {
        Schema::connection($this->connection)->create('platform_role_permissions', function (Blueprint $table) {
            $table->ulid('role_id');
            $table->ulid('permission_id');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);

            $table->foreign('role_id')
                ->references('id')
                ->on('platform_roles')
                ->noActionOnDelete();

            $table->foreign('permission_id')
                ->references('id')
                ->on('platform_permissions')
                ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('platform_role_permissions');
    }
};
