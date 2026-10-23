<?php

namespace App\Http\Requests;

use App\Models\GoogleSheetImportSetting;
use App\Services\GoogleSheetImporter;
use Closure;
use Cron\CronExpression;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePlanImportRequest extends FormRequest
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
        $setting = $this->route('googleSheetImportSetting');

        return [
            'api_key' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => GoogleSheetImportSetting::effectiveApiKey(null, $setting instanceof GoogleSheetImportSetting ? $setting : null) === null),
            ],
            'spreadsheet_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'sheet_name' => ['required', 'string', 'max:255'],
            'first_row' => ['required', 'integer', 'min:1', 'max:10000'],
            'last_row' => ['required', 'integer', 'gte:first_row', 'max:10000'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'auto_import' => ['sometimes', 'boolean'],
            'cron_expression' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! CronExpression::isValidExpression($value)) {
                        $fail('The cron expression is not valid.');
                    }
                },
            ],
        ];
    }
}
