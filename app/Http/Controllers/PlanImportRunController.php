<?php

namespace App\Http\Controllers;

use App\Models\GoogleSheetImportSetting;
use App\Services\GoogleSheetImporter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlanImportRunController extends Controller
{
    /**
     * Run a saved import again.
     */
    public function __invoke(GoogleSheetImportSetting $googleSheetImportSetting, GoogleSheetImporter $importer): RedirectResponse
    {
        $result = $importer->import($googleSheetImportSetting);

        Inertia::flash('success', 'Import finished: '.GoogleSheetImporter::summarize($result));

        if ($result['skippedRows'] !== []) {
            Inertia::flash('warning', 'Skipped rows without a valid or unique date: '.implode(', ', $result['skippedRows']).'.');
        }

        return redirect()->route('plan-import.index', ['import' => $googleSheetImportSetting->id]);
    }
}
