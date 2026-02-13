<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('audit-log.table', 'audit_logs');
        $connection = config('audit-log.connection');

        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->id();

            // Who
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_type')->nullable(); // For polymorphic users
            $table->string('user_name')->nullable(); // Cached user name
            $table->string('user_email')->nullable(); // Cached user email

            // When
            $table->timestamp('performed_at')->useCurrent()->index();

            // Where (Request info)
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable()->index(); // GET, POST, PUT, DELETE
            $table->string('route_name')->nullable()->index();

            // What (Action info)
            $table->string('event')->index(); // created, updated, deleted, login, etc.
            $table->string('auditable_type')->nullable()->index(); // Model class
            $table->unsignedBigInteger('auditable_id')->nullable()->index(); // Model ID
            $table->string('table_name')->nullable()->index(); // Database table

            // Changes
            $table->json('old_values')->nullable(); // Before changes
            $table->json('new_values')->nullable(); // After changes
            $table->json('changed_fields')->nullable(); // List of changed field names

            // Extra
            $table->json('request_data')->nullable(); // Request payload
            $table->json('metadata')->nullable(); // Any additional data
            $table->text('description')->nullable(); // Human readable description

            // Response (optional)
            $table->integer('response_code')->nullable();

            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'performed_at']);
            $table->index(['event', 'performed_at']);
        });
    }

    public function down(): void
    {
        $tableName = config('audit-log.table', 'audit_logs');
        $connection = config('audit-log.connection');

        Schema::connection($connection)->dropIfExists($tableName);
    }
};
