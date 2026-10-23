<?php

namespace App\Http\Requests;

use App\Models\PlanEvent;
use App\Models\PlanEventCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePlanEventObjectRequest extends FormRequest
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
            'plan_event_id' => [Rule::requiredIf($this->isMethod('POST')), Rule::exists(PlanEvent::class, 'id')],
            'plan_event_category_id' => ['required', Rule::exists(PlanEventCategory::class, 'id')],
            'text' => ['required', 'string', 'max:255'],
        ];
    }
}
