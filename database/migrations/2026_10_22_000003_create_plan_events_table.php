<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The columns that hold the user assigned to a position of an event.
     *
     * @var list<string>
     */
    private const ASSIGNMENT_COLUMNS = ['operator_1', 'operator_2', 'operator_3', 'engineer_1', 'engineer_2', 'engineer_3'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->text('name')->nullable();
            $table->boolean('show_original_text')->default(true);
            $table->boolean('original_text_changed')->default(false);
            $table->foreignUuid('google_sheet_import_id')
                ->nullable()
                ->unique()
                ->constrained('google_sheet_import')
                ->nullOnDelete();

            foreach (self::ASSIGNMENT_COLUMNS as $column) {
                $table->foreignUuid($column)->nullable()->constrained('users')->nullOnDelete();
            }

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_events');
    }
};
