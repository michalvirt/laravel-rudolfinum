<?php

use App\Enums\Permission;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\EmailNotificationActionSettingController;
use App\Http\Controllers\EmailNotificationController;
use App\Http\Controllers\EmailNotificationSendController;
use App\Http\Controllers\PermissionGroupNotificationPermissionController;
use App\Http\Controllers\PlanCalendarAvailabilityController;
use App\Http\Controllers\PlanCalendarEditController;
use App\Http\Controllers\PlanCalendarStaffVisibilityController;
use App\Http\Controllers\PlanCalendarViewController;
use App\Http\Controllers\PlanEventAssignmentController;
use App\Http\Controllers\PlanEventCategoryController;
use App\Http\Controllers\PlanEventChangeAcknowledgementController;
use App\Http\Controllers\PlanEventController;
use App\Http\Controllers\PlanEventObjectController;
use App\Http\Controllers\PlanEventObjectOrderController;
use App\Http\Controllers\PlanImportChangeController;
use App\Http\Controllers\PlanImportController;
use App\Http\Controllers\PlanImportRunController;
use App\Http\Controllers\PlanImportTabsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePasswordController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfilePasswordController::class, 'update'])->name('profile.password.update');

    Route::get('/calendar', CalendarController::class)
        ->middleware('can:'.Permission::ViewCalendar->value)
        ->name('calendar');
    Route::get('/plan-calendar-view', PlanCalendarViewController::class)
        ->middleware('can:'.Permission::ViewPlanCalendar->value)
        ->name('plan-calendar-view');
    Route::put('/plan-calendar-view/availability', PlanCalendarAvailabilityController::class)
        ->middleware('can:'.Permission::ViewPlanCalendar->value)
        ->name('plan-calendar-view.availability');
    Route::post('/plan-events/{planEvent}/change-acknowledgement', PlanEventChangeAcknowledgementController::class)
        ->middleware('can:'.Permission::ViewPlanCalendar->value)
        ->name('plan-events.change-acknowledgement');

    Route::middleware('can:'.Permission::EditPlanCalendar->value)->group(function () {
        Route::get('/plan-calendar-edit', PlanCalendarEditController::class)->name('plan-calendar-edit');
        Route::put('/plan-calendar-edit/staff-visibility', PlanCalendarStaffVisibilityController::class)
            ->name('plan-calendar-edit.staff-visibility');
        Route::resource('plan-events', PlanEventController::class)
            ->parameters(['plan-events' => 'planEvent'])
            ->only(['store', 'update', 'destroy']);
        Route::put('/plan-events/{planEvent}/assignments', PlanEventAssignmentController::class)
            ->name('plan-events.assignments');
        Route::put('/plan-events/{planEvent}/objects/order', PlanEventObjectOrderController::class)
            ->name('plan-events.objects.order');
        Route::resource('plan-event-objects', PlanEventObjectController::class)
            ->parameters(['plan-event-objects' => 'planEventObject'])
            ->only(['store', 'update', 'destroy']);
    });

    Route::middleware('can:'.Permission::ManagePlanCategories->value)->group(function () {
        Route::resource('plan-categories', PlanEventCategoryController::class)
            ->parameters(['plan-categories' => 'planEventCategory'])
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('can:'.Permission::ManagePlanImport->value)->group(function () {
        Route::post('/plan-import/tabs', PlanImportTabsController::class)->name('plan-import.tabs');
        Route::resource('plan-import', PlanImportController::class)
            ->parameters(['plan-import' => 'googleSheetImportSetting'])
            ->only(['index', 'store', 'update', 'destroy']);
        Route::post('/plan-import/{googleSheetImportSetting}/run', PlanImportRunController::class)
            ->name('plan-import.run');
        Route::post('/plan-import/{googleSheetImportSetting}/changes/accept', [PlanImportChangeController::class, 'acceptAll'])
            ->name('plan-import.changes.accept-all');
        Route::post('/plan-import/changes/{googleSheetImport}/accept', [PlanImportChangeController::class, 'accept'])
            ->name('plan-import.changes.accept');
    });

    Route::middleware('can:'.Permission::ManageEmailNotifications->value)->group(function () {
        Route::get('/email-notifications', [EmailNotificationController::class, 'index'])
            ->name('email-notifications.index');
        Route::put('/email-notifications/actions/{emailNotificationActionSetting}', EmailNotificationActionSettingController::class)
            ->name('email-notifications.actions.update');
        Route::post('/email-notifications/send-pending', [EmailNotificationSendController::class, 'pending'])
            ->name('email-notifications.send-pending');
        Route::post('/email-notifications/send-failed', [EmailNotificationSendController::class, 'failed'])
            ->name('email-notifications.send-failed');
        Route::post('/email-notifications/{emailNotificationLog}/send', [EmailNotificationSendController::class, 'one'])
            ->name('email-notifications.send');
        Route::delete('/email-notifications/{emailNotificationLog}', [EmailNotificationController::class, 'destroy'])
            ->name('email-notifications.destroy');
    });

    Route::middleware('can:'.Permission::ManageAccounts->value)->group(function () {
        Route::resource('accounts', AccountController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::put('/accounts/{account}/password', [AccountPasswordController::class, 'update'])
            ->name('accounts.password.update');
        Route::put('/permission-groups/{permissionGroup}/notification-permissions', PermissionGroupNotificationPermissionController::class)
            ->name('permission-groups.notification-permissions.update');
    });
});
