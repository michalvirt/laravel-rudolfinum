<?php

use App\Enums\EmailNotificationAction;
use App\Enums\EmailNotificationLanguage;
use App\Enums\EmailNotificationSendMode;
use App\Enums\ImportStatus;
use App\Enums\Permission;
use App\Mail\EmailNotificationMail;
use App\Models\EmailNotificationActionSetting;
use App\Models\EmailNotificationLog;
use App\Models\GoogleSheetImport;
use App\Models\GoogleSheetImportSetting;
use App\Models\PermissionGroup;
use App\Models\PlanEvent;
use App\Models\PlanMonthSetting;
use App\Models\User;
use App\Services\EmailNotifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->administrator = User::factory()->for(PermissionGroup::factory()->administrators())->create();
    $this->receiver = User::factory()
        ->for(PermissionGroup::factory()->create(['permissions' => [Permission::ReceiveImportGoogleSheetNotification]]))
        ->create(['email' => 'receiver@example.com']);
    $this->planner = User::factory()->for(PermissionGroup::factory()->planners())->create();

    $this->importSetting = GoogleSheetImportSetting::factory()->create(['sheet_name' => 'Listopad']);
    $this->result = ['created' => 3, 'changed' => 2, 'unchanged' => 10, 'skippedRows' => []];
});

test('it redirects guests to the login form', function () {
    $log = EmailNotificationLog::factory()->create();

    $this->get(route('email-notifications.index'))->assertRedirect(route('login'));
    $this->put(route('email-notifications.actions.update', EmailNotificationActionSetting::for(EmailNotificationAction::ImportGoogleSheet)))->assertRedirect(route('login'));
    $this->post(route('email-notifications.send', $log))->assertRedirect(route('login'));
    $this->post(route('email-notifications.send-pending'))->assertRedirect(route('login'));
    $this->post(route('email-notifications.send-failed'))->assertRedirect(route('login'));
    $this->delete(route('email-notifications.destroy', $log))->assertRedirect(route('login'));
});

test('it forbids users without the permission to manage the notifications', function () {
    $log = EmailNotificationLog::factory()->create();
    $actionSetting = EmailNotificationActionSetting::for(EmailNotificationAction::ImportGoogleSheet);

    $this->actingAs($this->planner);

    $this->get(route('email-notifications.index'))->assertForbidden();
    $this->put(route('email-notifications.actions.update', $actionSetting), ['label' => 'Renamed', 'background_color' => '#000000', 'font_color' => '#ffffff', 'send_mode' => 'immediate', 'language' => 'cs', 'batch_interval_minutes' => 5, 'receiver_permission' => 'calendar.view', 'is_enabled' => true])->assertForbidden();
    $this->post(route('email-notifications.send', $log))->assertForbidden();
    $this->post(route('email-notifications.send-pending'))->assertForbidden();
    $this->post(route('email-notifications.send-failed'))->assertForbidden();
    $this->delete(route('email-notifications.destroy', $log))->assertForbidden();

    expect(EmailNotificationLog::count())->toBe(1)
        ->and($actionSetting->fresh()->label)->toBe('Google Sheet import');
});

test('it notifies only users with the receiver permission', function () {
    Mail::fake();
    EmailNotificationActionSetting::factory()->immediate()->create();

    $logs = app(EmailNotifier::class)->notifyImportSucceeded($this->importSetting, $this->result);

    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('receiver_email')->all())->toEqualCanonicalizing(['receiver@example.com', $this->administrator->email]);
    Mail::assertSent(EmailNotificationMail::class, 2);
    Mail::assertSent(EmailNotificationMail::class, fn (EmailNotificationMail $mail): bool => $mail->hasTo('receiver@example.com'));
    Mail::assertNotSent(EmailNotificationMail::class, fn (EmailNotificationMail $mail): bool => $mail->hasTo($this->planner->email));
});

test('it writes the import status and the row counts into the English notification', function () {
    EmailNotificationActionSetting::factory()->create();

    app(EmailNotifier::class)->notifyImportSucceeded($this->importSetting, [...$this->result, 'skippedRows' => [16, 20]]);

    $log = EmailNotificationLog::where('receiver_id', $this->receiver->id)->sole();

    expect($log->action)->toBe(EmailNotificationAction::ImportGoogleSheet)
        ->and($log->language)->toBe(EmailNotificationLanguage::English)
        ->and($log->subject)->toBe('Google Sheet import of Listopad finished')
        ->and($log->body)->toContain('Import status: Success', 'New rows: 3', 'Changed rows: 2', 'Unchanged rows: 10', 'Skipped rows without a valid or unique date: 16, 20');
});

test('it writes the notification in the language of the settings', function () {
    EmailNotificationActionSetting::factory()->czech()->create();

    app(EmailNotifier::class)->notifyImportSucceeded($this->importSetting, $this->result);

    $log = EmailNotificationLog::where('receiver_id', $this->receiver->id)->sole();

    expect($log->language)->toBe(EmailNotificationLanguage::Czech)
        ->and($log->subject)->toBe('Import z Google Sheet (Listopad) byl dokončen')
        ->and($log->body)->toContain('Stav importu: Úspěch', 'Nové řádky: 3', 'Změněné řádky: 2', 'Beze změny: 10');
});

test('it writes a failed import into the notification', function () {
    EmailNotificationActionSetting::factory()->create();

    app(EmailNotifier::class)->notifyImportFailed($this->importSetting, 'The sheet is not shared.');

    $log = EmailNotificationLog::where('receiver_id', $this->receiver->id)->sole();

    expect($log->subject)->toBe('Google Sheet import of Listopad failed')
        ->and($log->body)->toContain('Import status: Failed', 'Error: The sheet is not shared.');
});

test('it keeps the notifications waiting in batch mode', function () {
    Mail::fake();
    EmailNotificationActionSetting::factory()->create();

    app(EmailNotifier::class)->notifyImportSucceeded($this->importSetting, $this->result);

    Mail::assertNothingSent();
    expect(EmailNotificationLog::where('is_sent', false)->whereNull('last_attempt_at')->count())->toBe(2);
});

test('it sends the waiting notifications once the batch interval has passed', function () {
    Mail::fake();
    $actionSetting = EmailNotificationActionSetting::factory()->create(['batch_interval_minutes' => 5, 'last_batch_sent_at' => now()->subMinutes(3)]);
    EmailNotificationLog::factory()->count(2)->create();

    $this->artisan('email-notifications:send')->assertSuccessful();

    Mail::assertNothingSent();

    $this->travel(3)->minutes();
    $this->artisan('email-notifications:send')->assertSuccessful();

    Mail::assertSent(EmailNotificationMail::class, 2);
    expect(EmailNotificationLog::where('is_sent', true)->count())->toBe(2)
        ->and($actionSetting->fresh()->last_batch_sent_at->isSameMinute(now()))->toBeTrue();
});

test('it sends the waiting notifications on demand regardless of the interval', function () {
    Mail::fake();
    EmailNotificationActionSetting::factory()->create(['last_batch_sent_at' => now()]);
    EmailNotificationLog::factory()->create();

    $this->artisan('email-notifications:send --force')->assertSuccessful();

    Mail::assertSent(EmailNotificationMail::class, 1);
});

test('it records a failed send with the error and does not retry it automatically', function () {
    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Connection refused'));
    $log = EmailNotificationLog::factory()->create();

    expect(app(EmailNotifier::class)->sendPending())->toBe(0);

    $log->refresh();
    expect($log->is_sent)->toBeFalse()
        ->and($log->state())->toBe('failed')
        ->and($log->attempts)->toBe(1)
        ->and($log->error_message)->toBe('Connection refused')
        ->and(app(EmailNotifier::class)->sendPending())->toBe(0);
});

test('it resends a failed notification', function () {
    Mail::fake();
    $log = EmailNotificationLog::factory()->failed()->create(['receiver_email' => 'someone@example.com']);

    $this->actingAs($this->administrator)
        ->post(route('email-notifications.send', $log))
        ->assertRedirect();

    Mail::assertSent(EmailNotificationMail::class, fn (EmailNotificationMail $mail): bool => $mail->hasTo('someone@example.com'));
    $log->refresh();
    expect($log->is_sent)->toBeTrue()
        ->and($log->attempts)->toBe(2)
        ->and($log->error_message)->toBeNull();
});

test('it does not send an already sent notification again', function () {
    Mail::fake();
    $log = EmailNotificationLog::factory()->sent()->create();

    $this->actingAs($this->administrator)->post(route('email-notifications.send', $log))->assertRedirect();

    Mail::assertNothingSent();
    expect($log->fresh()->attempts)->toBe(1);
});

test('it resends every failed notification and sends every pending one', function () {
    Mail::fake();
    EmailNotificationLog::factory()->count(2)->failed()->create();
    EmailNotificationLog::factory()->count(3)->create();
    EmailNotificationLog::factory()->sent()->create();

    $this->actingAs($this->administrator)->post(route('email-notifications.send-failed'))->assertRedirect();

    Mail::assertSent(EmailNotificationMail::class, 2);
    expect(EmailNotificationLog::where('is_sent', false)->count())->toBe(3);

    $this->post(route('email-notifications.send-pending'))->assertRedirect();

    Mail::assertSent(EmailNotificationMail::class, 5);
    expect(EmailNotificationLog::where('is_sent', false)->count())->toBe(0);
});

test('it deletes a notification', function () {
    $log = EmailNotificationLog::factory()->create();

    $this->actingAs($this->administrator)->delete(route('email-notifications.destroy', $log))->assertRedirect();

    expect(EmailNotificationLog::count())->toBe(0);
});

test('it lists the notifications and filters them by sending state', function () {
    EmailNotificationLog::factory()->sent()->count(2)->create();
    EmailNotificationLog::factory()->failed()->create();
    EmailNotificationLog::factory()->create();

    $this->actingAs($this->administrator)
        ->get(route('email-notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('email-notifications')
            ->has('notifications', 4)
            ->where('counts', ['sent' => 2, 'failed' => 1, 'pending' => 1])
            ->where('state', 'all')
            ->has('actionSettings', 2)
            ->where('actionSettings.0.action', 'import_google_sheet')
            ->where('actionSettings.0.label', 'Google Sheet import')
            ->where('actionSettings.0.background_color', '#1565c0')
            ->where('actionSettings.0.font_color', '#ffffff')
            ->where('actionSettings.0.send_mode', 'batch')
            ->where('actionSettings.0.language', 'en')
            ->where('actionSettings.0.batch_interval_minutes', 5)
            ->where('actionSettings.0.receiver_permission', 'importGoogleSheet.notification')
            ->where('actionSettings.0.is_enabled', true)
            ->where('options.permissions', array_column(Permission::cases(), 'value')));

    $this->get(route('email-notifications.index', ['state' => 'failed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications', 1)
            ->where('notifications.0.state', 'failed')
            ->where('state', 'failed'));
});

test('it saves the settings of one notification', function () {
    $actionSetting = EmailNotificationActionSetting::for(EmailNotificationAction::ImportGoogleSheet);

    $this->actingAs($this->administrator)
        ->put(route('email-notifications.actions.update', $actionSetting), [
            'label' => 'Plan import',
            'background_color' => '#ff0000',
            'font_color' => '#000000',
            'send_mode' => 'immediate',
            'language' => 'cs',
            'batch_interval_minutes' => 15,
            'receiver_permission' => 'calendar.view',
            'is_enabled' => '0',
        ])
        ->assertRedirect();

    $actionSetting->refresh();

    expect($actionSetting->label)->toBe('Plan import')
        ->and($actionSetting->background_color)->toBe('#ff0000')
        ->and($actionSetting->font_color)->toBe('#000000')
        ->and($actionSetting->send_mode)->toBe(EmailNotificationSendMode::Immediate)
        ->and($actionSetting->language)->toBe(EmailNotificationLanguage::Czech)
        ->and($actionSetting->batch_interval_minutes)->toBe(15)
        ->and($actionSetting->receiver_permission)->toBe(Permission::ViewCalendar)
        ->and($actionSetting->is_enabled)->toBeFalse();
});

test('it validates the settings of one notification', function (array $payload, string $field) {
    $actionSetting = EmailNotificationActionSetting::for(EmailNotificationAction::ImportGoogleSheet);

    $this->actingAs($this->administrator)
        ->put(route('email-notifications.actions.update', $actionSetting), [
            'label' => 'Plan import',
            'background_color' => '#ff0000',
            'font_color' => '#000000',
            'send_mode' => 'batch',
            'language' => 'en',
            'batch_interval_minutes' => 5,
            'receiver_permission' => 'calendar.view',
            'is_enabled' => true,
            ...$payload,
        ])
        ->assertSessionHasErrors($field);
})->with([
    'missing label' => [['label' => ''], 'label'],
    'too long label' => [['label' => str_repeat('a', 101)], 'label'],
    'unknown mode' => [['send_mode' => 'weekly'], 'send_mode'],
    'invalid background color' => [['background_color' => 'red'], 'background_color'],
    'invalid font color' => [['font_color' => '#fff'], 'font_color'],
    'unknown language' => [['language' => 'de'], 'language'],
    'zero interval' => [['batch_interval_minutes' => 0], 'batch_interval_minutes'],
    'too long interval' => [['batch_interval_minutes' => 1441], 'batch_interval_minutes'],
    'unknown permission' => [['receiver_permission' => 'everything.do'], 'receiver_permission'],
    'invalid enabled flag' => [['is_enabled' => 'maybe'], 'is_enabled'],
]);

test('it notifies the users with the receiver permission chosen for the notification', function () {
    EmailNotificationActionSetting::factory()->create(['receiver_permission' => Permission::ViewCalendar]);

    $logs = app(EmailNotifier::class)->notifyImportSucceeded($this->importSetting, $this->result);

    expect($logs->pluck('receiver_email')->all())->toEqualCanonicalizing([$this->administrator->email, $this->planner->email]);
});

test('it notifies a user with the receiver permission set on the user', function () {
    Mail::fake();
    EmailNotificationActionSetting::factory()->immediate()->create();
    $viewer = User::factory()
        ->for(PermissionGroup::factory()->viewers())
        ->create(['permissions' => [Permission::ReceiveImportGoogleSheetNotification]]);

    $logs = app(EmailNotifier::class)->notifyImportSucceeded($this->importSetting, $this->result);

    expect($logs->pluck('receiver_email')->all())->toEqualCanonicalizing([
        $this->administrator->email,
        'receiver@example.com',
        $viewer->email,
    ]);
    Mail::assertSent(EmailNotificationMail::class, 3);
});

test('it notifies a user once when the user and the group have the receiver permission', function () {
    Mail::fake();
    EmailNotificationActionSetting::factory()->immediate()->create();
    $this->receiver->update(['permissions' => [Permission::ReceiveImportGoogleSheetNotification]]);

    $logs = app(EmailNotifier::class)->notifyImportSucceeded($this->importSetting, $this->result);

    expect($logs->where('receiver_id', $this->receiver->id))->toHaveCount(1)
        ->and($logs)->toHaveCount(2);
    Mail::assertSent(EmailNotificationMail::class, 2);
    expect(Mail::sent(EmailNotificationMail::class, fn (EmailNotificationMail $mail): bool => $mail->hasTo('receiver@example.com')))->toHaveCount(1);
});

test('it notifies a user without a group when the user has the receiver permission', function () {
    EmailNotificationActionSetting::factory()->create();
    $account = User::factory()->create([
        'permission_group_id' => null,
        'permissions' => [Permission::ReceiveImportGoogleSheetNotification],
    ]);

    $logs = app(EmailNotifier::class)->notifyImportSucceeded($this->importSetting, $this->result);

    expect($logs->pluck('receiver_id')->all())->toContain($account->id);
});

test('it creates nothing for a disabled notification', function () {
    Mail::fake();
    EmailNotificationActionSetting::factory()->immediate()->disabled()->create();

    $logs = app(EmailNotifier::class)->notifyImportSucceeded($this->importSetting, $this->result);

    expect($logs)->toBeEmpty()
        ->and(EmailNotificationLog::count())->toBe(0);
    Mail::assertNothingSent();
});

test('it sends the waiting notifications of an immediate notification without waiting for the batch interval', function () {
    Mail::fake();
    EmailNotificationActionSetting::factory()->immediate()->create(['batch_interval_minutes' => 60, 'last_batch_sent_at' => now()]);
    EmailNotificationLog::factory()->create();

    $this->artisan('email-notifications:send')->assertSuccessful();

    Mail::assertSent(EmailNotificationMail::class, 1);
});

test('it uses the batch interval of the notification', function () {
    Mail::fake();
    EmailNotificationActionSetting::factory()->create(['batch_interval_minutes' => 30, 'last_batch_sent_at' => now()->subMinutes(20)]);
    EmailNotificationLog::factory()->create();

    $this->artisan('email-notifications:send')->assertSuccessful();
    Mail::assertNothingSent();

    $this->travel(10)->minutes();
    $this->artisan('email-notifications:send')->assertSuccessful();

    Mail::assertSent(EmailNotificationMail::class, 1);
});

test('it creates notifications when a Google Sheet import finishes', function () {
    EmailNotificationActionSetting::factory()->create();
    Http::preventStrayRequests();
    Http::fake([
        'sheets.googleapis.com/v4/spreadsheets/sheet-id/values/*' => Http::response(['values' => [
            ['1.11.', 'ne', 'služba', '13:30-15:30 zkouška ČF'],
            ['2.11.', 'po', 'služba', 'instalace světel'],
        ]]),
    ]);

    $this->artisan('google-sheet:import', ['import' => $this->importSetting->id])->assertSuccessful();

    $log = EmailNotificationLog::where('receiver_id', $this->receiver->id)->sole();

    expect($this->importSetting->fresh()->last_import_status)->toBe(ImportStatus::Success)
        ->and(EmailNotificationLog::count())->toBe(2)
        ->and($log->body)->toContain('Import status: Success', 'New rows: 2', 'Changed rows: 0');
});

test('it creates notifications when a Google Sheet import fails', function () {
    EmailNotificationActionSetting::factory()->create();
    Http::preventStrayRequests();
    Http::fake(['sheets.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 403)]);

    $this->artisan('google-sheet:import', ['import' => $this->importSetting->id])->assertFailed();

    $log = EmailNotificationLog::where('receiver_id', $this->receiver->id)->sole();

    expect($log->body)->toContain('Import status: Failed', 'API key not valid');
});

test('it does not fail the import when the notification cannot be created', function () {
    $this->mock(EmailNotifier::class)
        ->shouldReceive('notifyImportSucceeded')
        ->once()
        ->andThrow(new RuntimeException('Notification broke'));
    Http::preventStrayRequests();
    Http::fake(['sheets.googleapis.com/*' => Http::response(['values' => [['1.11.', 'ne', 'služba', 'zkouška']]])]);

    $this->artisan('google-sheet:import', ['import' => $this->importSetting->id])->assertSuccessful();

    expect($this->importSetting->fresh()->last_import_status)->toBe(ImportStatus::Success);
});

/**
 * Create a changed sheet row paired with a plan event of 5 November 2026 that has an operator and an engineer assigned.
 *
 * @param  array<string, string|null>  $changedFields
 * @return array{GoogleSheetImport, PlanEvent, User, User}
 */
function changedEventWithStaff(array $changedFields = ['name' => 'Nový název', 'comment' => 'Dveře v 18:00']): array
{
    $import = GoogleSheetImport::factory()
        ->changed($changedFields)
        ->create(['date' => '2026-11-05', 'name' => 'Starý název', 'comment' => null]);
    $group = PermissionGroup::factory()->viewers()->create();
    $operator = User::factory()->for($group)->create();
    $engineer = User::factory()->for($group)->create();
    $event = PlanEvent::factory()->create([
        'date' => '2026-11-05',
        'name' => 'Starý název',
        'google_sheet_import_id' => $import->id,
        'operator_1' => $operator->id,
        'engineer_1' => $engineer->id,
    ]);

    return [$import, $event, $operator, $engineer];
}

test('it notifies the assigned operator and engineer when a change is accepted in a month with the staff shown', function () {
    Mail::fake();
    EmailNotificationActionSetting::factory()->immediate()->create();
    EmailNotificationActionSetting::factory()->immediate()->create(['action' => EmailNotificationAction::PlanEventChange, 'receiver_permission' => Permission::ReceivePlanEventChangeNotification]);
    PlanMonthSetting::factory()->staffShown()->create(['month' => '2026-11']);
    [$import, , $operator, $engineer] = changedEventWithStaff();
    $unassigned = User::factory()->for($operator->permissionGroup)->create();

    $this->actingAs($this->administrator)
        ->post(route('plan-import.changes.accept', $import))
        ->assertRedirect();

    $logs = EmailNotificationLog::where('action', EmailNotificationAction::PlanEventChange)->get();

    expect($logs->pluck('receiver_id')->all())->toEqualCanonicalizing([$operator->id, $engineer->id])
        ->and($logs->first()->subject)->toBe('The event on 2026-11-05 changed')
        ->and($logs->first()->body)->toContain('Event name: Starý název -> Nový název')
        ->and($logs->first()->body)->toContain('Comment: (empty) -> Dveře v 18:00');
    Mail::assertSent(EmailNotificationMail::class, 2);
    Mail::assertNotSent(EmailNotificationMail::class, fn (EmailNotificationMail $mail): bool => $mail->hasTo($unassigned->email));
});

test('it does not notify the assigned users when the staff of the month is hidden', function () {
    [$import] = changedEventWithStaff();
    PlanMonthSetting::factory()->create(['month' => '2026-11', 'staff_visible' => false]);

    $this->actingAs($this->administrator)->post(route('plan-import.changes.accept', $import));

    expect(EmailNotificationLog::where('action', EmailNotificationAction::PlanEventChange)->count())->toBe(0);
});

test('it does not notify when the event of the accepted change has nobody assigned', function () {
    PlanMonthSetting::factory()->staffShown()->create(['month' => '2026-11']);
    $import = GoogleSheetImport::factory()->changed()->create(['date' => '2026-11-05']);
    PlanEvent::factory()->create(['date' => '2026-11-05', 'google_sheet_import_id' => $import->id]);

    $this->actingAs($this->administrator)->post(route('plan-import.changes.accept', $import));

    expect(EmailNotificationLog::where('action', EmailNotificationAction::PlanEventChange)->count())->toBe(0);
});

test('it does not notify assigned users without the receiver permission or when the notification is disabled', function () {
    PlanMonthSetting::factory()->staffShown()->create(['month' => '2026-11']);
    [$import, $event, $operator, $engineer] = changedEventWithStaff();
    $operator->update(['permission_group_id' => PermissionGroup::factory()->create(['permissions' => [Permission::ViewCalendar]])->id]);

    $this->actingAs($this->administrator)->post(route('plan-import.changes.accept', $import));

    expect(EmailNotificationLog::where('action', EmailNotificationAction::PlanEventChange)->pluck('receiver_id')->all())->toBe([$engineer->id]);

    EmailNotificationLog::query()->delete();
    EmailNotificationActionSetting::for(EmailNotificationAction::PlanEventChange)->update(['is_enabled' => false]);
    $second = GoogleSheetImport::factory()->changed()->create(['date' => '2026-11-06']);
    PlanEvent::factory()->create(['date' => '2026-11-06', 'google_sheet_import_id' => $second->id, 'engineer_1' => $engineer->id]);

    $this->post(route('plan-import.changes.accept', $second));

    expect(EmailNotificationLog::count())->toBe(0);
});

test('it notifies the assigned users of every accepted day when all changes are accepted', function () {
    PlanMonthSetting::factory()->staffShown()->create(['month' => '2026-11']);
    [, , $operator] = changedEventWithStaff();
    $setting = GoogleSheetImportSetting::factory()->create(['sheet_name' => 'Listopad']);

    $this->actingAs($this->administrator)->post(route('plan-import.changes.accept-all', $setting));

    expect(EmailNotificationLog::where('action', EmailNotificationAction::PlanEventChange)->where('receiver_id', $operator->id)->count())->toBe(1);
});
