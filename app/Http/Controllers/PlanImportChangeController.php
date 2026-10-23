<?php

namespace App\Http\Controllers;

use App\Models\GoogleSheetImport;
use App\Models\GoogleSheetImportSetting;
use App\Services\GoogleSheetImporter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlanImportChangeController extends Controller
{
    /**
     * Accept the changed values of one row and copy them to the plan calendar.
     */
    public function accept(GoogleSheetImport $googleSheetImport, GoogleSheetImporter $importer): RedirectResponse
    {
        $importer->accept($googleSheetImport);

        Inertia::flash('success', 'Changes of '.$googleSheetImport->date->toDateString().' were accepted.');

        return back();
    }

    /**
     * Accept the changed values of every row of an import.
     */
    public function acceptAll(GoogleSheetImportSetting $googleSheetImportSetting, GoogleSheetImporter $importer): RedirectResponse
    {
        $imports = $googleSheetImportSetting->importedRows()->whereNotNull('changed_fields')->get();

        $imports->each(fn (GoogleSheetImport $import) => $importer->accept($import));

        Inertia::flash('success', "Accepted changes of {$imports->count()} rows.");

        return back();
    }
}
