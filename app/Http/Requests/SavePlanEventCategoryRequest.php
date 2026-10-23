<?php

namespace App\Http\Requests;

use App\Models\PlanEventCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePlanEventCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => [
                'required',
                'string',
                'max:100',
                Rule::unique(PlanEventCategory::class)->ignore($this->route('planEventCategory')),
            ],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
