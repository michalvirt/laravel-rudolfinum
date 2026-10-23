<?php

namespace Database\Factories;

use App\Enums\EmailNotificationAction;
use App\Enums\EmailNotificationLanguage;
use App\Models\EmailNotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailNotificationLog>
 */
class EmailNotificationLogFactory extends Factory
{
    /**
     * Define the model's default state. The notification is waiting to be sent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => EmailNotificationAction::ImportGoogleSheet,
            'receiver_id' => null,
            'receiver_email' => fake()->unique()->safeEmail(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'language' => EmailNotificationLanguage::English,
            'is_sent' => false,
            'attempts' => 0,
            'last_attempt_at' => null,
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the email was sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => true,
            'attempts' => 1,
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Indicate that sending the email failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => false,
            'attempts' => 1,
            'last_attempt_at' => now(),
            'error_message' => 'Connection could not be established.',
        ]);
    }
}
