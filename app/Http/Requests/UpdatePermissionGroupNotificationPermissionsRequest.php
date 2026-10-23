<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionGroupNotificationPermissionsRequest extends FormRequest
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
            'permissions' => ['array'],
            'permissions.*' => ['distinct', Rule::enum(Permission::class)->only(Permission::notifications())],
        ];
    }

    /**
     * Get the validated notification permissions.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return array_values(array_map(
            fn (string $value): Permission => Permission::from($value),
            $this->validated('permissions'),
        ));
    }

    /**
     * Treat a form without any checked permission as an empty list.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['permissions' => []]);
    }
}
