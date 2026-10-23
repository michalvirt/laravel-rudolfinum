<?php

namespace App\Enums;

enum Permission: string
{
    case ViewCalendar = 'calendar.view';
    case ViewPlanCalendar = 'plan-calendar.view';
    case ManageAccounts = 'accounts.manage';
    case ManagePlanImport = 'plan-import.manage';
    case EditPlanCalendar = 'plan-calendar.edit';
    case ManagePlanCategories = 'plan-categories.manage';
    case ManageEmailNotifications = 'email-notifications.manage';
    case ReceiveImportGoogleSheetNotification = 'importGoogleSheet.notification';
    case ReceivePlanEventChangeNotification = 'planEventChange.notification';

    /**
     * Determine whether the permission only makes the user receive a notification (its value ends with `.notification`).
     */
    public function isNotification(): bool
    {
        return str_ends_with($this->value, '.notification');
    }

    /**
     * The permissions that can be set on a single user and not only on a permission group.
     *
     * @return list<self>
     */
    public static function notifications(): array
    {
        return array_values(array_filter(self::cases(), fn (self $permission): bool => $permission->isNotification()));
    }

    /**
     * The name shown on the accounts page.
     */
    public function label(): string
    {
        return match ($this) {
            self::ReceiveImportGoogleSheetNotification => 'Google Sheet import notification',
            self::ReceivePlanEventChangeNotification => 'Plan event change notification',
            default => $this->value,
        };
    }
}
