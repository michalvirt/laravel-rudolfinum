<?php

namespace App\Http\Requests;

use App\Models\GoogleSheetImportSetting;
use App\Services\GoogleSheetImporter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowPlanImportTabsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept a pasted spreadsheet URL as well as the bare spreadsheet id.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'spreadsheet_id' => GoogleSheetImporter::extractSpreadsheetId((string) $this->input('spreadsheet_id')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'api_key' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => GoogleSheetImportSetting::effectiveApiKey(null, $this->savedSetting()) === null),
            ],
            'spreadsheet_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'import_id' => ['nullable', Rule::exists(GoogleSheetImportSetting::class, 'id')],
        ];
    }

    /**
     * The saved import whose API key is used when none is submitted.
     */
    public function savedSetting(): ?GoogleSheetImportSetting
    {
        return $this->filled('import_id')
            ? GoogleSheetImportSetting::find($this->string('import_id')->toString())
            : null;
    }
}
