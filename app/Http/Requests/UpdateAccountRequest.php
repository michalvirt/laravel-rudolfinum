<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Models\PermissionGroup;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->route('account')),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'permission_group_id' => ['nullable', Rule::exists(PermissionGroup::class, 'id')],
            'permissions' => ['array'],
            'permissions.*' => ['distinct', Rule::enum(Permission::class)->only(Permission::notifications())],
        ];
    }

    /**
     * Treat a form without any checked notification permission as an empty list.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['permissions' => []]);
    }
}
