<?php

namespace App\Models;

use App\Enums\EmailNotificationAction;
use App\Enums\EmailNotificationLanguage;
use Database\Factories\EmailNotificationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One email notification for one receiver. The row is created when the action happens and records
 * whether the email was sent. A row that was never attempted is waiting for the next batch send.
 *
 * @property string $id
 * @property EmailNotificationAction $action what created the notification
 * @property string|null $receiver_id
 * @property string $receiver_email
 * @property string $subject
 * @property string $body
 * @property EmailNotificationLanguage $language
 * @property bool $is_sent
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $receiver
 */
#[Table('email_notifications_log')]
#[Fillable([
    'action', 'receiver_id', 'receiver_email', 'subject', 'body', 'language',
    'is_sent', 'attempts', 'last_attempt_at', 'error_message',
])]
class EmailNotificationLog extends Model
{
    /** @use HasFactory<EmailNotificationLogFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'action' => EmailNotificationAction::class,
            'language' => EmailNotificationLanguage::class,
            'is_sent' => 'boolean',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * The state of the notification: `sent`, `failed` or `pending` when no send was attempted yet.
     *
     * @return 'sent'|'failed'|'pending'
     */
    public function state(): string
    {
        return match (true) {
            $this->is_sent => 'sent',
            $this->last_attempt_at === null => 'pending',
            default => 'failed',
        };
    }
}
