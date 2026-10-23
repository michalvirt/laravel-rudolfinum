<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowPlanImportTabsRequest;
use App\Models\GoogleSheetImportSetting;
use App\Services\GoogleSheetImporter;
use Illuminate\Http\JsonResponse;

class PlanImportTabsController extends Controller
{
    /**
     * List the tabs of a spreadsheet so one can be picked before the import is saved.
     */
    public function __invoke(ShowPlanImportTabsRequest $request, GoogleSheetImporter $importer): JsonResponse
    {
        $apiKey = GoogleSheetImportSetting::effectiveApiKey(
            $request->string('api_key')->toString(),
            $request->savedSetting(),
        );

        return response()->json([
            'sheet_names' => $importer->fetchSheetNames((string) $apiKey, $request->string('spreadsheet_id')->toString()),
        ]);
    }
}
