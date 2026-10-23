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
        Schema::create('email_notification_action_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action')->unique();
            $table->string('label');
            $table->string('background_color', 7)->default('#1565c0');
            $table->string('font_color', 7)->default('#ffffff');
            $table->string('send_mode')->default('batch');
            $table->string('language', 2)->default('en');
            $table->unsignedSmallInteger('batch_interval_minutes')->default(5);
            $table->timestamp('last_batch_sent_at')->nullable();
            $table->string('receiver_permission');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_notification_action_settings');
    }
};
