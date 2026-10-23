<?php

namespace App\Enums;

/**
 * The actions that create an email notification. The value is stored in the log and is the key of the
 * template in `lang/{language}/email_notifications.php`. How each action is sent is stored in
 * `App\Models\EmailNotificationActionSetting`.
 */
enum EmailNotificationAction: string
{
    case ImportGoogleSheet = 'import_google_sheet';
    case PlanEventChange = 'plan_event_change';

    /**
     * The name of the action shown until an administrator renames it.
     */
    public function defaultLabel(): string
    {
        return match ($this) {
            self::ImportGoogleSheet => 'Google Sheet import',
            self::PlanEventChange => 'Plan event change',
        };
    }

    /**
     * The permission a user needs to receive the notifications of the action until an administrator changes it.
     */
    public function defaultReceiverPermission(): Permission
    {
        return match ($this) {
            self::ImportGoogleSheet => Permission::ReceiveImportGoogleSheetNotification,
            self::PlanEventChange => Permission::ReceivePlanEventChangeNotification,
        };
    }
}
