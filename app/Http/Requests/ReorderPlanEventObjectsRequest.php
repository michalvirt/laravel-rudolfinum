<?php

namespace App\Http\Requests;

use App\Models\PlanEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderPlanEventObjectsRequest extends FormRequest
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
            'objects' => ['required', 'array'],
            'objects.*' => ['required', 'string', 'distinct'],
        ];
    }

    /**
     * Get the after validation callbacks: the order must list every object of the event exactly once.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var PlanEvent $planEvent */
                $planEvent = $this->route('planEvent');
                $expected = $planEvent->objects()->pluck('id')->sort()->values()->all();
                $submitted = collect($this->array('objects'))->sort()->values()->all();

                if ($expected !== $submitted) {
                    $validator->errors()->add('objects', 'The order must contain every object of the event exactly once.');
                }
            },
        ];
    }

    /**
     * The ids of the objects in the requested order.
     *
     * @return list<string>
     */
    public function orderedIds(): array
    {
        return array_values(array_map(strval(...), $this->array('objects')));
    }
}
