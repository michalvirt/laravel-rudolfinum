<?php

namespace App\Http\Controllers;

use App\Enums\EmailNotificationLanguage;
use App\Enums\EmailNotificationSendMode;
use App\Enums\Permission;
use App\Models\EmailNotificationActionSetting;
use App\Models\EmailNotificationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailNotificationController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * List the logged email notifications, newest first, together with the settings of every notification.
     */
    public function index(Request $request): Response
    {
        $state = in_array($request->query('state'), ['sent', 'failed', 'pending'], true)
            ? (string) $request->query('state')
            : 'all';

        $notifications = EmailNotificationLog::query()
            ->when($state === 'sent', fn (Builder $query) => $query->where('is_sent', true))
            ->when($state === 'failed', fn (Builder $query) => $query->where('is_sent', false)->whereNotNull('last_attempt_at'))
            ->when($state === 'pending', fn (Builder $query) => $query->where('is_sent', false)->whereNull('last_attempt_at'))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('email-notifications', [
            'notifications' => $notifications->getCollection()
                ->map(fn (EmailNotificationLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action->value,
                    'receiver_email' => $log->receiver_email,
                    'subject' => $log->subject,
                    'body' => $log->body,
                    'language' => $log->language->value,
                    'state' => $log->state(),
                    'attempts' => $log->attempts,
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'last_attempt_at' => $log->last_attempt_at?->toIso8601String(),
                ])
                ->all(),
            'pagination' => [
                'page' => $notifications->currentPage(),
                'perPage' => self::PER_PAGE,
                'total' => $notifications->total(),
            ],
            'state' => $state,
            'counts' => [
                'sent' => EmailNotificationLog::where('is_sent', true)->count(),
                'failed' => EmailNotificationLog::where('is_sent', false)->whereNotNull('last_attempt_at')->count(),
                'pending' => EmailNotificationLog::where('is_sent', false)->whereNull('last_attempt_at')->count(),
            ],
            'actionSettings' => EmailNotificationActionSetting::allWithDefaults()
                ->map(fn (EmailNotificationActionSetting $actionSetting): array => [
                    'id' => $actionSetting->id,
                    'action' => $actionSetting->action->value,
                    'label' => $actionSetting->label,
                    'background_color' => $actionSetting->background_color,
                    'font_color' => $actionSetting->font_color,
                    'send_mode' => $actionSetting->send_mode->value,
                    'language' => $actionSetting->language->value,
                    'batch_interval_minutes' => $actionSetting->batch_interval_minutes,
                    'last_batch_sent_at' => $actionSetting->last_batch_sent_at?->toIso8601String(),
                    'receiver_permission' => $actionSetting->receiver_permission->value,
                    'is_enabled' => $actionSetting->is_enabled,
                ])
                ->all(),
            'options' => [
                'sendModes' => array_column(EmailNotificationSendMode::cases(), 'value'),
                'languages' => array_column(EmailNotificationLanguage::cases(), 'value'),
                'permissions' => array_column(Permission::cases(), 'value'),
            ],
        ]);
    }

    /**
     * Delete a logged notification.
     */
    public function destroy(EmailNotificationLog $emailNotificationLog): RedirectResponse
    {
        $emailNotificationLog->delete();

        Inertia::flash('success', "Notification for {$emailNotificationLog->receiver_email} was deleted.");

        return back();
    }
}
