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
        Schema::create('google_sheet_import_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('api_key')->nullable();
            $table->string('spreadsheet_id')->nullable();
            $table->json('sheet_names')->nullable();
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('first_row')->default(15);
            $table->unsignedInteger('last_row')->default(40);
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('auto_import')->default(false);
            $table->string('cron_expression')->default('0 * * * *');
            $table->timestamp('last_imported_at')->nullable();
            $table->string('last_import_status')->nullable();
            $table->text('last_import_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_sheet_import_settings');
    }
};
