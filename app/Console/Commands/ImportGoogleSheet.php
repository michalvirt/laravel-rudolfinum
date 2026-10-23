<?php

namespace App\Console\Commands;

use App\Models\GoogleSheetImportSetting;
use App\Services\GoogleSheetImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportGoogleSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google-sheet:import {import? : The id of one saved import to run right now}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the saved Google Sheet imports that are due, or one import right now';

    /**
     * Execute the console command.
     */
    public function handle(GoogleSheetImporter $importer): int
    {
        $imports = filled($this->argument('import'))
            ? GoogleSheetImportSetting::whereKey($this->argument('import'))->get()
            : GoogleSheetImportSetting::where('auto_import', true)->get()
                ->filter(fn (GoogleSheetImportSetting $setting): bool => $setting->isImportDue());

        if (filled($this->argument('import')) && $imports->isEmpty()) {
            $this->error('The import does not exist.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($imports as $setting) {
            try {
                $result = $importer->import($setting);
            } catch (ValidationException $exception) {
                $this->error("{$setting->sheet_name}: ".implode(' ', array_merge(...array_values($exception->errors()))));
                $failed = true;

                continue;
            }

            $this->info("{$setting->sheet_name}: ".GoogleSheetImporter::summarize($result));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
