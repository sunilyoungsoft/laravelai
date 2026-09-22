<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'platform';

    public function up(): void
    {
        Schema::connection($this->connection)->create('platform_user_roles', function (Blueprint $table) {
            $table->ulid('user_id');
            $table->ulid('role_id');
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('platform_users')
                ->noActionOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('platform_roles')
                ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('platform_user_roles');
    }
};
