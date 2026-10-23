<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_notifications_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action');
            $table->foreignUuid('receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receiver_email');
            $table->string('subject');
            $table->text('body');
            $table->string('language', 2);
            $table->boolean('is_sent')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['is_sent', 'last_attempt_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_notifications_log');
    }
};
