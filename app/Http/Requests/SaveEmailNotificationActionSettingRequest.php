<?php

namespace App\Http\Requests;

use App\Enums\EmailNotificationLanguage;
use App\Enums\EmailNotificationSendMode;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmailNotificationActionSettingRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:100'],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'send_mode' => ['required', Rule::enum(EmailNotificationSendMode::class)],
            'language' => ['required', Rule::enum(EmailNotificationLanguage::class)],
            'batch_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'receiver_permission' => ['required', Rule::enum(Permission::class)],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
