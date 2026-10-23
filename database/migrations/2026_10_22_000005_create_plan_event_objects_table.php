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
        Schema::create('plan_event_objects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_event_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_event_category_id')->constrained('plan_event_categories')->restrictOnDelete();
            $table->string('text');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_event_objects');
    }
};
