<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'platform';

    public function up(): void
    {
        Schema::connection($this->connection)->create('companies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug', 63)->unique();
            $table->string('database_name', 64)->unique();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('secondary_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('postal_code')->nullable();
            $table->string('status')->default('pending');
            $table->ulid('created_by');
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');

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
        Schema::connection($this->connection)->dropIfExists('companies');
    }
};
