<?php

use App\Enums\ImportStatus;
use App\Models\GoogleSheetImport;
use App\Models\GoogleSheetImportSetting;
use App\Models\PlanEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * @param  list<list<string>>  $values
 */
function fakeSheetValues(array $values): void
{
    Http::preventStrayRequests();
    Http::fake(['sheets.googleapis.com/*' => Http::response(['values' => $values])]);
}

test('it runs one import right now from the command', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    fakeSheetValues([['1.11.', 'ne', 'služba', 'Koncert', '', 'Jan Salát']]);

    $this->artisan('google-sheet:import', ['import' => $setting->id])->assertSuccessful();

    expect(GoogleSheetImport::count())->toBe(1)
        ->and(PlanEvent::where('name', 'Koncert')->count())->toBe(1);
});

test('it fails the command for an unknown import', function () {
    $this->artisan('google-sheet:import', ['import' => 'missing'])->assertFailed();
});

test('it fails the command when an import cannot run', function () {
    $setting = GoogleSheetImportSetting::factory()->create(['sheet_name' => null]);

    $this->artisan('google-sheet:import', ['import' => $setting->id])->assertFailed();
});

test('it runs only the automatic imports that are due', function () {
    $due = GoogleSheetImportSetting::factory()->automatic('0 * * * *')->create(['sheet_name' => 'Listopad']);
    $later = GoogleSheetImportSetting::factory()->automatic('30 * * * *')->create(['sheet_name' => 'Prosinec']);
    $manual = GoogleSheetImportSetting::factory()->create(['sheet_name' => 'Říjen', 'cron_expression' => '0 * * * *']);
    fakeSheetValues([['1.11.', 'ne', 'služba', 'Koncert']]);

    $this->travelTo('2026-11-01 10:00:00');

    $this->artisan('google-sheet:import')->assertSuccessful();

    expect($due->refresh()->last_imported_at)->not->toBeNull()
        ->and($later->refresh()->last_imported_at)->toBeNull()
        ->and($manual->refresh()->last_imported_at)->toBeNull();
});

test('it keeps running the other due imports when one fails', function () {
    $broken = GoogleSheetImportSetting::factory()->automatic()->create(['sheet_name' => null, 'created_at' => now()->subDay()]);
    $working = GoogleSheetImportSetting::factory()->automatic()->create();
    fakeSheetValues([['1.11.', 'ne', 'služba', 'Koncert']]);

    $this->travelTo('2026-11-01 10:00:00');

    $this->artisan('google-sheet:import')->assertFailed();

    expect($working->refresh()->last_import_message)->toBe('1 new, 0 changed, 0 unchanged.')
        ->and($broken->refresh()->last_import_status)->toBe(ImportStatus::Failed);
});

test('it does nothing without saved imports', function () {
    Http::fake();

    $this->artisan('google-sheet:import')->assertSuccessful();

    Http::assertNothingSent();
});

test('it is due only when the automatic import is on and the cron expression matches', function (bool $automatic, string $cron, string $now, bool $due) {
    $setting = GoogleSheetImportSetting::factory()->create(['auto_import' => $automatic, 'cron_expression' => $cron]);

    expect($setting->isImportDue(Carbon::parse($now)))->toBe($due);
})->with([
    'hourly at the start of the hour' => [true, '0 * * * *', '2026-11-01 10:00:00', true],
    'hourly during the hour' => [true, '0 * * * *', '2026-11-01 10:30:00', false],
    'every quarter hour' => [true, '*/15 * * * *', '2026-11-01 10:45:00', true],
    'switched off' => [false, '0 * * * *', '2026-11-01 10:00:00', false],
]);

test('it schedules the due imports to be checked every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'google-sheet:import'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
