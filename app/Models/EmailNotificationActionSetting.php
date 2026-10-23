<?php

namespace App\Models;

use App\Enums\EmailNotificationAction;
use App\Enums\EmailNotificationLanguage;
use App\Enums\EmailNotificationSendMode;
use App\Enums\Permission;
use Database\Factories\EmailNotificationActionSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How the email notifications of one action are written, sent and who receives them.
 *
 * @property string $id
 * @property EmailNotificationAction $action
 * @property string $label the name of the action shown to the administrators
 * @property string $background_color hex color of the label, e.g. #1565c0
 * @property string $font_color hex color of the text of the label
 * @property EmailNotificationSendMode $send_mode
 * @property EmailNotificationLanguage $language the language of the notification templates
 * @property int $batch_interval_minutes how many minutes pass between two batch sends
 * @property Carbon|null $last_batch_sent_at
 * @property Permission $receiver_permission the permission a user needs to receive the notifications
 * @property bool $is_enabled whether the action creates notifications at all
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['action', 'label', 'background_color', 'font_color', 'send_mode', 'language', 'batch_interval_minutes', 'last_batch_sent_at', 'receiver_permission', 'is_enabled'])]
class EmailNotificationActionSetting extends Model
{
    /** @use HasFactory<EmailNotificationActionSettingFactory> */
    use HasFactory, HasUuids;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'background_color' => '#1565c0',
        'font_color' => '#ffffff',
        'send_mode' => 'batch',
        'language' => 'en',
        'batch_interval_minutes' => 5,
        'is_enabled' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'action' => EmailNotificationAction::class,
            'send_mode' => EmailNotificationSendMode::class,
            'language' => EmailNotificationLanguage::class,
            'batch_interval_minutes' => 'integer',
            'last_batch_sent_at' => 'datetime',
            'receiver_permission' => Permission::class,
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * Get the settings of an action and create them with the defaults when they do not exist yet.
     */
    public static function for(EmailNotificationAction $action): self
    {
        return self::query()->firstOrCreate(['action' => $action], [
            'label' => $action->defaultLabel(),
            'receiver_permission' => $action->defaultReceiverPermission(),
        ]);
    }

    /**
     * Get the settings of every action, ordered by their label, and create the missing ones.
     *
     * @return Collection<int, self>
     */
    public static function allWithDefaults(): Collection
    {
        foreach (EmailNotificationAction::cases() as $action) {
            self::for($action);
        }

        return self::query()->orderBy('label')->get();
    }

    /**
     * Determine whether the waiting emails of the action should be sent now.
     */
    public function isBatchDue(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->last_batch_sent_at === null
            || $this->last_batch_sent_at->addMinutes($this->batch_interval_minutes)->lte($at);
    }
}
