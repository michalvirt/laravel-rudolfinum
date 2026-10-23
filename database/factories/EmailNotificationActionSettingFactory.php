<?php

namespace Database\Factories;

use App\Enums\EmailNotificationAction;
use App\Enums\EmailNotificationLanguage;
use App\Enums\EmailNotificationSendMode;
use App\Enums\Permission;
use App\Models\EmailNotificationActionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailNotificationActionSetting>
 */
class EmailNotificationActionSettingFactory extends Factory
{
    /**
     * Define the model's default state: the settings an action gets when it is used for the first time.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => EmailNotificationAction::ImportGoogleSheet,
            'label' => EmailNotificationAction::ImportGoogleSheet->defaultLabel(),
            'background_color' => '#1565c0',
            'font_color' => '#ffffff',
            'send_mode' => EmailNotificationSendMode::Batch,
            'language' => EmailNotificationLanguage::English,
            'batch_interval_minutes' => 5,
            'receiver_permission' => Permission::ReceiveImportGoogleSheetNotification,
            'is_enabled' => true,
        ];
    }

    /**
     * Indicate that the emails are sent right after the action created them.
     */
    public function immediate(): static
    {
        return $this->state(fn (array $attributes) => [
            'send_mode' => EmailNotificationSendMode::Immediate,
        ]);
    }

    /**
     * Indicate that the notifications are written in Czech.
     */
    public function czech(): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => EmailNotificationLanguage::Czech,
        ]);
    }

    /**
     * Indicate that the action does not create notifications.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
