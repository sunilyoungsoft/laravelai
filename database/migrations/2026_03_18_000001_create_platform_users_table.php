<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'platform';

    public function up(): void
    {
        Schema::connection($this->connection)->create('platform_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_failed_login')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

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
        Schema::connection($this->connection)->dropIfExists('platform_users');
    }
};
