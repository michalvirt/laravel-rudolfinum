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
        Schema::create('plan_event_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label')->unique();
            $table->string('background_color', 7);
            $table->string('font_color', 7);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_event_categories');
    }
};
