<?php

namespace App\Services;

use App\Enums\EmailNotificationAction;
use App\Enums\EmailNotificationSendMode;
use App\Mail\EmailNotificationMail;
use App\Models\EmailNotificationActionSetting;
use App\Models\EmailNotificationLog;
use App\Models\GoogleSheetImportSetting;
use App\Models\PermissionGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates the email notifications in the log and sends them, immediately or in a batch.
 */
class EmailNotifier
{
    /**
     * Notify the receivers that an import finished.
     *
     * @param  array{created: int, changed: int, unchanged: int, skippedRows: list<int>}  $result
     * @return Collection<int, EmailNotificationLog>
     */
    public function notifyImportSucceeded(GoogleSheetImportSetting $import, array $result): Collection
    {
        $language = EmailNotificationActionSetting::for(EmailNotificationAction::ImportGoogleSheet)->language->value;
        $skipped = $result['skippedRows'] === []
            ? ''
            : "\n".trans(
                'email_notifications.import_google_sheet.success.skipped',
                ['rows' => implode(', ', $result['skippedRows'])],
                $language,
            );

        return $this->notify(EmailNotificationAction::ImportGoogleSheet, 'success', [
            'sheet' => (string) $import->sheet_name,
            'status' => trans('email_notifications.statuses.success', [], $language),
            'created' => $result['created'],
            'changed' => $result['changed'],
            'unchanged' => $result['unchanged'],
            'skipped' => $skipped,
        ]);
    }

    /**
     * Notify the receivers that an import failed.
     *
     * @return Collection<int, EmailNotificationLog>
     */
    public function notifyImportFailed(GoogleSheetImportSetting $import, string $message): Collection
    {
        return $this->notify(EmailNotificationAction::ImportGoogleSheet, 'failed', [
            'sheet' => (string) $import->sheet_name,
            'status' => trans('email_notifications.statuses.failed', [], EmailNotificationActionSetting::for(EmailNotificationAction::ImportGoogleSheet)->language->value),
            'message' => $message,
        ]);
    }

    /**
     * Notify the given users, the operators and engineers assigned to a day, that the event of the day changed.
     * Only those of them who have the receiver permission are notified.
     *
     * @param  array<string, array{old: string|null, new: string|null}>  $changes  the changed fields of the sheet row, keyed by field
     * @param  list<string>  $assignedUserIds
     * @return Collection<int, EmailNotificationLog>
     */
    public function notifyPlanEventChanged(string $date, ?string $eventName, array $changes, array $assignedUserIds): Collection
    {
        $language = EmailNotificationActionSetting::for(EmailNotificationAction::PlanEventChange)->language->value;
        $empty = trans('email_notifications.plan_event_change.empty', [], $language);

        $lines = collect($changes)
            ->map(fn (array $change, string $field): string => trans('email_notifications.plan_event_change.change', [
                'field' => trans("email_notifications.fields.{$field}", [], $language),
                'old' => $change['old'] ?? $empty,
                'new' => $change['new'] ?? $empty,
            ], $language))
            ->implode("\n");

        return $this->notify(EmailNotificationAction::PlanEventChange, 'changed', [
            'date' => $date,
            'name' => $eventName ?? $empty,
            'changes' => $lines,
        ], $assignedUserIds);
    }

    /**
     * Send a logged notification and record the outcome. Returns whether the email was sent.
     */
    public function send(EmailNotificationLog $log): bool
    {
        $values = ['attempts' => $log->attempts + 1, 'last_attempt_at' => now()];

        try {
            Mail::to($log->receiver_email)->send(new EmailNotificationMail($log->subject, $log->body));
        } catch (Throwable $exception) {
            $log->update([...$values, 'is_sent' => false, 'error_message' => Str::limit($exception->getMessage(), 500)]);

            return false;
        }

        $log->update([...$values, 'is_sent' => true, 'error_message' => null]);

        return true;
    }

    /**
     * Send every notification that no send was attempted for yet, optionally only the ones of some actions.
     * Returns the number of sent emails.
     *
     * @param  list<EmailNotificationAction>|null  $actions
     */
    public function sendPending(?array $actions = null): int
    {
        return $this->sendUnsent(failed: false, actions: $actions);
    }

    /**
     * Send again every notification whose send failed. Returns the number of sent emails.
     */
    public function resendFailed(): int
    {
        return $this->sendUnsent(failed: true);
    }

    /**
     * Get the users that receive the notifications of an action: the users that have its receiver permission
     * themselves and the users whose group has it. A user with both is listed once, so receives one notification.
     *
     * @param  list<string>|null  $onlyUserIds  keep only these users, or every user with the permission when null
     * @return Collection<int, User>
     */
    public function receivers(EmailNotificationAction $action, ?array $onlyUserIds = null): Collection
    {
        $permission = EmailNotificationActionSetting::for($action)->receiver_permission;

        $groupIds = PermissionGroup::all()
            ->filter(fn (PermissionGroup $group): bool => $group->grants($permission))
            ->pluck('id');

        return User::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('permission_group_id', $groupIds)
                ->orWhereJsonContains('permissions', $permission->value))
            ->when($onlyUserIds !== null, fn (Builder $query) => $query->whereIn('id', $onlyUserIds))
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
    }

    /**
     * Write a notification for every receiver and send them right away when the action is sent immediately.
     * A disabled action creates nothing.
     *
     * @param  array<string, string|int>  $replacements  the values of the placeholders of the template
     * @param  list<string>|null  $onlyUserIds  notify only these users, or every receiver of the action when null
     * @return Collection<int, EmailNotificationLog>
     */
    private function notify(EmailNotificationAction $action, string $outcome, array $replacements, ?array $onlyUserIds = null): Collection
    {
        $actionSetting = EmailNotificationActionSetting::for($action);

        if (! $actionSetting->is_enabled) {
            return new Collection;
        }

        $language = $actionSetting->language->value;

        $logs = $this->receivers($action, $onlyUserIds)->map(fn (User $receiver): EmailNotificationLog => EmailNotificationLog::create([
            'action' => $action,
            'receiver_id' => $receiver->id,
            'receiver_email' => $receiver->email,
            'subject' => trans("email_notifications.{$action->value}.{$outcome}.subject", $replacements, $language),
            'body' => trans("email_notifications.{$action->value}.{$outcome}.body", $replacements, $language),
            'language' => $actionSetting->language,
        ]));

        if ($actionSetting->send_mode === EmailNotificationSendMode::Immediate) {
            $logs->each(fn (EmailNotificationLog $log): bool => $this->send($log));
        }

        return $logs;
    }

    /**
     * Send the unsent notifications: the ones whose send failed, or the ones that were never attempted.
     *
     * @param  list<EmailNotificationAction>|null  $actions  only the notifications of these actions, or all when null
     */
    private function sendUnsent(bool $failed, ?array $actions = null): int
    {
        $sent = 0;

        EmailNotificationLog::where('is_sent', false)
            ->when($actions !== null, fn (Builder $query) => $query->whereIn('action', $actions))
            ->when(
                $failed,
                fn (Builder $query) => $query->whereNotNull('last_attempt_at'),
                fn (Builder $query) => $query->whereNull('last_attempt_at'),
            )
            ->lazyById()
            ->each(function (EmailNotificationLog $log) use (&$sent): void {
                $sent += $this->send($log) ? 1 : 0;
            });

        return $sent;
    }
}
