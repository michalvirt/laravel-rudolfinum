<?php

namespace App\Console\Commands;

use App\Enums\EmailNotificationSendMode;
use App\Models\EmailNotificationActionSetting;
use App\Services\EmailNotifier;
use Illuminate\Console\Command;

class SendEmailNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email-notifications:send {--force : Send the waiting emails of batch notifications even when their batch interval has not passed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send the email notifications that are waiting in the log';

    /**
     * Execute the console command.
     */
    public function handle(EmailNotifier $notifier): int
    {
        $sent = 0;

        foreach (EmailNotificationActionSetting::allWithDefaults() as $actionSetting) {
            $isImmediate = $actionSetting->send_mode === EmailNotificationSendMode::Immediate;

            if (! $isImmediate && ! $this->option('force') && ! $actionSetting->isBatchDue()) {
                continue;
            }

            $sent += $notifier->sendPending([$actionSetting->action]);

            if (! $isImmediate) {
                $actionSetting->update(['last_batch_sent_at' => now()]);
            }
        }

        $this->info("Sent {$sent} email notifications.");

        return self::SUCCESS;
    }
}
