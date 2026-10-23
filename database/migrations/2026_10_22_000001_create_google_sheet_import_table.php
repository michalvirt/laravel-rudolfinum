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
        Schema::create('google_sheet_import', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('spreadsheet_id');
            $table->string('sheet_name');
            $table->unsignedInteger('row_number');
            $table->date('date');
            $table->string('weekday')->nullable();
            $table->string('service')->nullable();
            $table->text('name')->nullable();
            $table->text('comment')->nullable();
            $table->string('operator_1')->nullable();
            $table->string('operator_2')->nullable();
            $table->string('htl_1')->nullable();
            $table->string('htl_2')->nullable();
            $table->json('changed_fields')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['spreadsheet_id', 'sheet_name', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_sheet_import');
    }
};
