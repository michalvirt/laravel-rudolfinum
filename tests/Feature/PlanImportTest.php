<?php

use App\Enums\ImportStatus;
use App\Models\GoogleSheetImport;
use App\Models\GoogleSheetImportSetting;
use App\Models\PermissionGroup;
use App\Models\PlanEvent;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'services.google_sheets.api_key' => null,
        'services.google_sheets.spreadsheet_id' => null,
    ]);

    $this->administrator = User::factory()->for(PermissionGroup::factory()->administrators())->create();
});

/**
 * Replace the fake responses of the Google Sheets API.
 *
 * @param  list<list<string>>|null  $values  the rows of the imported range
 * @param  list<string>|null  $tabs  the titles of the tabs of the spreadsheet
 */
function fakeGoogle(?array $values = null, ?array $tabs = null, int $status = 200): void
{
    app()->forgetInstance(Factory::class);
    Http::clearResolvedInstance(Factory::class);

    Http::preventStrayRequests();

    Http::fake([
        'sheets.googleapis.com/v4/spreadsheets/sheet-id/values/*' => Http::response(['values' => $values ?? []], $status),
        'sheets.googleapis.com/v4/spreadsheets/sheet-id?*' => Http::response([
            'sheets' => array_map(fn (string $title): array => ['properties' => ['title' => $title]], $tabs ?? ['Říjen', 'Listopad']),
        ], $status),
    ]);
}

/**
 * @return list<list<string>>
 */
function sheetRows(): array
{
    return [
        ['1.11.', 'ne', 'služba', '13:30-15:30 zkouška ČF', '', 'Jan Salát'],
        ['10.11.', 'út', 'služba', 'instalace světel', 'instalace', 'Michal Virt', 'Martin Vála', '', 'Jan Salát', 'Ivo Mechel'],
    ];
}

/**
 * @return array<string, mixed>
 */
function importPayload(array $overrides = []): array
{
    return array_filter([
        'api_key' => 'my-key',
        'spreadsheet_id' => 'sheet-id',
        'sheet_name' => 'Listopad',
        'first_row' => 15,
        'last_row' => 40,
        'year' => 2026,
        'auto_import' => '1',
        'cron_expression' => '*/15 * * * *',
        ...$overrides,
    ], fn (mixed $value): bool => $value !== null);
}

test('it redirects guests to the login form', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    $import = GoogleSheetImport::factory()->changed()->create();

    $this->get(route('plan-import.index'))->assertRedirect(route('login'));
    $this->post(route('plan-import.store'), importPayload())->assertRedirect(route('login'));
    $this->post(route('plan-import.tabs'))->assertRedirect(route('login'));
    $this->put(route('plan-import.update', $setting), importPayload())->assertRedirect(route('login'));
    $this->delete(route('plan-import.destroy', $setting))->assertRedirect(route('login'));
    $this->post(route('plan-import.run', $setting))->assertRedirect(route('login'));
    $this->post(route('plan-import.changes.accept-all', $setting))->assertRedirect(route('login'));
    $this->post(route('plan-import.changes.accept', $import))->assertRedirect(route('login'));
});

test('it forbids the import without the manage plan import permission', function (string $group) {
    Http::fake();

    $setting = GoogleSheetImportSetting::factory()->create();
    $import = GoogleSheetImport::factory()->changed()->create();
    $this->actingAs(User::factory()->for(PermissionGroup::factory()->{$group}())->create());

    $this->get(route('plan-import.index'))->assertForbidden();
    $this->post(route('plan-import.store'), importPayload())->assertForbidden();
    $this->post(route('plan-import.tabs'), ['spreadsheet_id' => 'sheet-id'])->assertForbidden();
    $this->put(route('plan-import.update', $setting), importPayload())->assertForbidden();
    $this->delete(route('plan-import.destroy', $setting))->assertForbidden();
    $this->post(route('plan-import.run', $setting))->assertForbidden();
    $this->post(route('plan-import.changes.accept-all', $setting))->assertForbidden();
    $this->post(route('plan-import.changes.accept', $import))->assertForbidden();

    Http::assertNothingSent();
    expect($import->refresh()->hasPendingChanges())->toBeTrue()
        ->and(GoogleSheetImportSetting::count())->toBe(1);
})->with(['planners', 'viewers']);

test('it lists every saved import with its settings', function () {
    config([
        'services.google_sheets.api_key' => 'default-key',
        'services.google_sheets.spreadsheet_id' => 'default-sheet-id',
    ]);

    $first = GoogleSheetImportSetting::factory()->automatic('*/15 * * * *')->create([
        'sheet_name' => 'Říjen',
        'created_at' => now()->subDay(),
    ]);
    $second = GoogleSheetImportSetting::factory()->create([
        'sheet_name' => 'Listopad',
        'last_imported_at' => now(),
        'last_import_status' => ImportStatus::Failed,
        'last_import_message' => 'Sheet rejected.',
    ]);
    GoogleSheetImport::factory()->changed()->create(['sheet_name' => 'Listopad']);
    GoogleSheetImport::factory()->changed()->create(['sheet_name' => 'Listopad']);
    GoogleSheetImport::factory()->changed()->create(['sheet_name' => 'Říjen']);

    $this->actingAs($this->administrator)
        ->get(route('plan-import.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plan-import')
            ->has('imports', 2)
            ->where('imports.0.id', $first->id)
            ->where('imports.0.sheet_name', 'Říjen')
            ->where('imports.0.auto_import', true)
            ->where('imports.0.cron_expression', '*/15 * * * *')
            ->where('imports.0.changedCount', 1)
            ->where('imports.0.hasApiKey', true)
            ->where('imports.1.id', $second->id)
            ->where('imports.1.first_row', 15)
            ->where('imports.1.last_row', 40)
            ->where('imports.1.year', 2026)
            ->where('imports.1.sheet_names', ['Říjen', 'Listopad'])
            ->where('imports.1.last_import_status', 'failed')
            ->where('imports.1.last_import_message', 'Sheet rejected.')
            ->where('imports.1.changedCount', 2)
            ->missing('imports.0.api_key')
            ->where('defaults.hasApiKey', true)
            ->where('defaults.spreadsheet_id', 'default-sheet-id')
            ->where('defaults.first_row', 15)
            ->where('defaults.last_row', 40)
        );
});

test('it shows the rows of the selected import and defaults to the first one', function () {
    $october = GoogleSheetImportSetting::factory()->create(['sheet_name' => 'Říjen', 'created_at' => now()->subDay()]);
    $november = GoogleSheetImportSetting::factory()->create(['sheet_name' => 'Listopad']);
    $changed = GoogleSheetImport::factory()->changed(['name' => 'Nový název'])->create(['sheet_name' => 'Listopad']);
    GoogleSheetImport::factory()->create(['sheet_name' => 'Listopad']);
    GoogleSheetImport::factory()->create(['sheet_name' => 'Říjen']);
    GoogleSheetImport::factory()->create(['sheet_name' => 'Listopad', 'spreadsheet_id' => 'other-sheet']);

    $this->actingAs($this->administrator)
        ->get(route('plan-import.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedId', $october->id)
            ->has('rows', 1)
        );

    $this->get(route('plan-import.index', ['import' => $november->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedId', $november->id)
            ->has('rows', 2)
            ->where('rows.0.id', $changed->id)
            ->where('rows.0.hasChanges', true)
            ->where('rows.0.changed_fields.name', 'Nový název')
            ->where('rows.1.hasChanges', false)
        );
});

test('it shows an empty list without saved imports', function () {
    $this->actingAs($this->administrator)
        ->get(route('plan-import.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('imports', 0)
            ->where('selectedId', null)
            ->has('rows', 0)
        );
});

test('it lists the tabs of a spreadsheet', function () {
    fakeGoogle(tabs: ['říjen 2026', 'listopad 2026']);

    $this->actingAs($this->administrator)
        ->postJson(route('plan-import.tabs'), [
            'api_key' => 'my-key',
            'spreadsheet_id' => 'https://docs.google.com/spreadsheets/d/sheet-id/edit#gid=0',
        ])
        ->assertOk()
        ->assertExactJson(['sheet_names' => ['říjen 2026', 'listopad 2026']]);

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', 'my-key')
        && $request->data() === ['fields' => 'sheets.properties.title']);
});

test('it uses the saved or the default api key when listing the tabs', function () {
    fakeGoogle();
    $setting = GoogleSheetImportSetting::factory()->create(['api_key' => 'saved-key']);

    $this->actingAs($this->administrator)
        ->postJson(route('plan-import.tabs'), ['spreadsheet_id' => 'sheet-id', 'import_id' => $setting->id])
        ->assertOk();

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', 'saved-key'));

    config(['services.google_sheets.api_key' => 'default-key']);
    fakeGoogle();

    $this->postJson(route('plan-import.tabs'), ['spreadsheet_id' => 'sheet-id'])->assertOk();

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', 'default-key'));
});

test('it validates the tab lookup', function (array $payload, string $field) {
    Http::fake();

    $this->actingAs($this->administrator)
        ->postJson(route('plan-import.tabs'), $payload)
        ->assertJsonValidationErrors($field);

    Http::assertNothingSent();
})->with([
    'missing api key' => [['spreadsheet_id' => 'sheet-id'], 'api_key'],
    'missing sheet id' => [['api_key' => 'my-key'], 'spreadsheet_id'],
    'invalid sheet id' => [['api_key' => 'my-key', 'spreadsheet_id' => 'not valid!'], 'spreadsheet_id'],
    'unknown import' => [['api_key' => 'my-key', 'spreadsheet_id' => 'sheet-id', 'import_id' => 'missing'], 'import_id'],
]);

test('it reports a rejected spreadsheet when listing the tabs', function () {
    fakeGoogle(status: 403);

    $this->actingAs($this->administrator)
        ->postJson(route('plan-import.tabs'), ['api_key' => 'my-key', 'spreadsheet_id' => 'sheet-id'])
        ->assertJsonValidationErrors('sheet');
});

test('it creates an import', function () {
    fakeGoogle();

    $response = $this->actingAs($this->administrator)
        ->post(route('plan-import.store'), importPayload(['spreadsheet_id' => 'https://docs.google.com/spreadsheets/d/sheet-id/edit']))
        ->assertSessionHasNoErrors();

    $setting = GoogleSheetImportSetting::firstOrFail();

    $response->assertRedirect(route('plan-import.index', ['import' => $setting->id]));

    expect(Str::isUuid($setting->id))->toBeTrue()
        ->and($setting->api_key)->toBe('my-key')
        ->and($setting->getRawOriginal('api_key'))->not->toBe('my-key')
        ->and($setting->spreadsheet_id)->toBe('sheet-id')
        ->and($setting->sheet_names)->toBe(['Říjen', 'Listopad'])
        ->and($setting->sheet_name)->toBe('Listopad')
        ->and($setting->first_row)->toBe(15)
        ->and($setting->last_row)->toBe(40)
        ->and($setting->year)->toBe(2026)
        ->and($setting->auto_import)->toBeTrue()
        ->and($setting->cron_expression)->toBe('*/15 * * * *')
        ->and($setting->last_imported_at)->toBeNull();
});

test('it creates more than one import', function () {
    fakeGoogle();

    $this->actingAs($this->administrator)->post(route('plan-import.store'), importPayload());
    $this->post(route('plan-import.store'), importPayload(['sheet_name' => 'Říjen', 'auto_import' => null]));

    expect(GoogleSheetImportSetting::orderBy('created_at')->pluck('sheet_name')->all())->toEqualCanonicalizing(['Listopad', 'Říjen'])
        ->and(GoogleSheetImportSetting::where('sheet_name', 'Říjen')->firstOrFail()->auto_import)->toBeFalse();
});

test('it creates an import with the default api key', function () {
    config(['services.google_sheets.api_key' => 'default-key']);
    fakeGoogle();

    $this->actingAs($this->administrator)
        ->post(route('plan-import.store'), importPayload(['api_key' => '']))
        ->assertSessionHasNoErrors();

    expect(GoogleSheetImportSetting::firstOrFail()->api_key)->toBe('default-key');

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', 'default-key'));
});

test('it validates a new import', function (array $overrides, string $field) {
    fakeGoogle();

    $this->actingAs($this->administrator)
        ->post(route('plan-import.store'), importPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(GoogleSheetImportSetting::count())->toBe(0);
})->with([
    'missing api key' => [['api_key' => ''], 'api_key'],
    'missing sheet id' => [['spreadsheet_id' => ''], 'spreadsheet_id'],
    'invalid sheet id' => [['spreadsheet_id' => 'not valid!'], 'spreadsheet_id'],
    'missing tab' => [['sheet_name' => ''], 'sheet_name'],
    'unknown tab' => [['sheet_name' => 'Neexistuje'], 'sheet_name'],
    'row from below one' => [['first_row' => 0], 'first_row'],
    'row to before row from' => [['first_row' => 20, 'last_row' => 10], 'last_row'],
    'invalid year' => [['year' => 1999], 'year'],
    'invalid cron expression' => [['cron_expression' => 'every day'], 'cron_expression'],
]);

test('it does not save an import of a rejected spreadsheet', function () {
    fakeGoogle(status: 403);

    $this->actingAs($this->administrator)
        ->post(route('plan-import.store'), importPayload())
        ->assertSessionHasErrors('sheet');

    expect(GoogleSheetImportSetting::count())->toBe(0);
});

test('it updates an import and keeps its saved api key', function () {
    fakeGoogle();
    $setting = GoogleSheetImportSetting::factory()->automatic()->create(['api_key' => 'saved-key', 'sheet_name' => 'Říjen']);

    $this->actingAs($this->administrator)
        ->put(route('plan-import.update', $setting), importPayload([
            'api_key' => '',
            'first_row' => 10,
            'last_row' => 30,
            'year' => 2027,
            'auto_import' => null,
            'cron_expression' => '0 6 * * *',
        ]))
        ->assertRedirect(route('plan-import.index', ['import' => $setting->id]))
        ->assertSessionHasNoErrors();

    $setting->refresh();

    expect(GoogleSheetImportSetting::count())->toBe(1)
        ->and($setting->api_key)->toBe('saved-key')
        ->and($setting->sheet_name)->toBe('Listopad')
        ->and($setting->first_row)->toBe(10)
        ->and($setting->last_row)->toBe(30)
        ->and($setting->year)->toBe(2027)
        ->and($setting->auto_import)->toBeFalse()
        ->and($setting->cron_expression)->toBe('0 6 * * *');

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', 'saved-key'));
});

test('it replaces the api key of an import when a new one is submitted', function () {
    fakeGoogle();
    $setting = GoogleSheetImportSetting::factory()->create(['api_key' => 'saved-key']);

    $this->actingAs($this->administrator)
        ->put(route('plan-import.update', $setting), importPayload(['api_key' => 'new-key']))
        ->assertSessionHasNoErrors();

    expect($setting->refresh()->api_key)->toBe('new-key');
});

test('it validates an updated import', function () {
    fakeGoogle();
    $setting = GoogleSheetImportSetting::factory()->create();

    $this->actingAs($this->administrator)
        ->put(route('plan-import.update', $setting), importPayload(['cron_expression' => 'nope']))
        ->assertSessionHasErrors('cron_expression');

    $this->put(route('plan-import.update', $setting), importPayload(['sheet_name' => 'Neexistuje', 'year' => 2030]))
        ->assertSessionHasErrors('sheet_name');

    expect($setting->refresh()->sheet_name)->toBe('Listopad')
        ->and($setting->year)->toBe(2026);
});

test('it deletes an import and keeps the imported rows and the plan calendar', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    $import = GoogleSheetImport::factory()->create();
    $planEvent = PlanEvent::factory()->create(['google_sheet_import_id' => $import->id]);

    $this->actingAs($this->administrator)
        ->delete(route('plan-import.destroy', $setting))
        ->assertRedirect(route('plan-import.index'));

    $this->assertModelMissing($setting);
    $this->assertModelExists($import);
    $this->assertModelExists($planEvent);
});

test('it runs a saved import with generated uuids and copies the event names to the plan calendar', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    fakeGoogle(sheetRows());

    $this->actingAs($this->administrator)
        ->post(route('plan-import.run', $setting))
        ->assertRedirect(route('plan-import.index', ['import' => $setting->id]))
        ->assertSessionHasNoErrors();

    expect(GoogleSheetImport::count())->toBe(2)
        ->and(PlanEvent::count())->toBe(2);

    $first = GoogleSheetImport::whereDate('date', '2026-11-01')->firstOrFail();
    $second = GoogleSheetImport::whereDate('date', '2026-11-10')->firstOrFail();

    expect(Str::isUuid($first->id))->toBeTrue()
        ->and($first->spreadsheet_id)->toBe('sheet-id')
        ->and($first->sheet_name)->toBe('Listopad')
        ->and($first->row_number)->toBe(15)
        ->and($first->weekday)->toBe('ne')
        ->and($first->service)->toBe('služba')
        ->and($first->name)->toBe('13:30-15:30 zkouška ČF')
        ->and($first->comment)->toBeNull()
        ->and($first->operator_1)->toBe('Jan Salát')
        ->and($first->operator_2)->toBeNull()
        ->and($first->changed_fields)->toBeNull()
        ->and($second->row_number)->toBe(16)
        ->and($second->comment)->toBe('instalace')
        ->and($second->operator_2)->toBe('Martin Vála')
        ->and($second->htl_1)->toBe('Jan Salát')
        ->and($second->htl_2)->toBe('Ivo Mechel')
        ->and($first->planEvent->name)->toBe('13:30-15:30 zkouška ČF')
        ->and($first->planEvent->date->toDateString())->toBe('2026-11-01')
        ->and($second->planEvent->name)->toBe('instalace světel');

    expect($setting->refresh()->last_import_status)->toBe(ImportStatus::Success)
        ->and($setting->last_import_message)->toBe('2 new, 0 changed, 0 unchanged.');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->hasHeader('X-Goog-Api-Key', 'test-key')
        && str_ends_with($request->url(), '/values/'.rawurlencode("'Listopad'!A15:J40")));
});

test('it runs the saved rows range of the import', function () {
    $setting = GoogleSheetImportSetting::factory()->create(['sheet_name' => "Říjen's", 'first_row' => 20, 'last_row' => 25]);
    fakeGoogle([['1.11.', 'ne', 'služba', 'Koncert']]);

    $this->actingAs($this->administrator)->post(route('plan-import.run', $setting));

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/values/'.rawurlencode("'Říjen''s'!A20:J25")));
    expect(GoogleSheetImport::firstOrFail()->row_number)->toBe(20);
});

test('it runs the imports independently of each other', function () {
    $november = GoogleSheetImportSetting::factory()->create();
    $october = GoogleSheetImportSetting::factory()->create(['sheet_name' => 'Říjen']);
    fakeGoogle([['1.11.', 'ne', 'služba', 'Koncert']]);

    $this->actingAs($this->administrator)->post(route('plan-import.run', $november));

    expect($november->refresh()->last_imported_at)->not->toBeNull()
        ->and($october->refresh()->last_imported_at)->toBeNull()
        ->and(GoogleSheetImport::where('sheet_name', 'Listopad')->count())->toBe(1)
        ->and(GoogleSheetImport::where('sheet_name', 'Říjen')->count())->toBe(0);
});

test('it does not flag anything when an existing import matches the sheet', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    fakeGoogle(sheetRows());

    $this->actingAs($this->administrator)->post(route('plan-import.run', $setting));
    $this->post(route('plan-import.run', $setting))->assertSessionHasNoErrors();

    expect(GoogleSheetImport::count())->toBe(2)
        ->and(PlanEvent::count())->toBe(2)
        ->and(GoogleSheetImport::whereNotNull('changed_fields')->count())->toBe(0)
        ->and($setting->refresh()->last_import_message)->toBe('0 new, 0 changed, 2 unchanged.');
});

test('it flags the values that differ without touching the plan calendar', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    fakeGoogle(sheetRows());

    $this->actingAs($this->administrator)->post(route('plan-import.run', $setting));

    $changedRows = sheetRows();
    $changedRows[1][3] = 'jiný název';
    $changedRows[1][5] = 'Daniel Čechtický';
    fakeGoogle($changedRows);

    $this->post(route('plan-import.run', $setting))->assertSessionHasNoErrors();

    $unchanged = GoogleSheetImport::whereDate('date', '2026-11-01')->firstOrFail();
    $changed = GoogleSheetImport::whereDate('date', '2026-11-10')->firstOrFail();

    expect($unchanged->hasPendingChanges())->toBeFalse()
        ->and($changed->hasPendingChanges())->toBeTrue()
        ->and($changed->changed_fields)->toBe(['name' => 'jiný název', 'operator_1' => 'Daniel Čechtický'])
        ->and($changed->name)->toBe('instalace světel')
        ->and($changed->operator_1)->toBe('Michal Virt')
        ->and($changed->planEvent->name)->toBe('instalace světel')
        ->and(PlanEvent::count())->toBe(2)
        ->and($setting->refresh()->last_import_message)->toBe('0 new, 1 changed, 1 unchanged.');
});

test('it clears the flag when the sheet returns to the stored values', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    fakeGoogle(sheetRows());
    $this->actingAs($this->administrator)->post(route('plan-import.run', $setting));

    $changedRows = sheetRows();
    $changedRows[0][3] = 'jiný název';
    fakeGoogle($changedRows);
    $this->post(route('plan-import.run', $setting));

    expect(GoogleSheetImport::whereNotNull('changed_fields')->count())->toBe(1);

    fakeGoogle(sheetRows());
    $this->post(route('plan-import.run', $setting));

    expect(GoogleSheetImport::whereNotNull('changed_fields')->count())->toBe(0);
});

test('it flags a stored row that was cleared in the sheet and ignores new rows without content', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    fakeGoogle(sheetRows());
    $this->actingAs($this->administrator)->post(route('plan-import.run', $setting));

    fakeGoogle([
        ['1.11.', 'ne', 'služba'],
        ['10.11.', 'út', 'služba', 'instalace světel', 'instalace', 'Michal Virt', 'Martin Vála', '', 'Jan Salát', 'Ivo Mechel'],
        ['24.12.', 'čt', '', '', '', ''],
    ]);
    $this->post(route('plan-import.run', $setting));

    $cleared = GoogleSheetImport::whereDate('date', '2026-11-01')->firstOrFail();

    expect($cleared->changed_fields)->toBe(['name' => null, 'operator_1' => null])
        ->and(GoogleSheetImport::count())->toBe(2);
});

test('it accepts the changes of a row and copies the name to the plan calendar', function () {
    $import = GoogleSheetImport::factory()
        ->changed(['name' => 'Nový název', 'operator_1' => 'Jan Salát'])
        ->create(['name' => 'Starý název', 'operator_1' => 'Michal Virt']);
    $planEvent = PlanEvent::factory()->create(['date' => $import->date, 'name' => 'Starý název', 'google_sheet_import_id' => $import->id]);
    $other = GoogleSheetImport::factory()->changed()->create();

    $this->actingAs($this->administrator)
        ->post(route('plan-import.changes.accept', $import))
        ->assertRedirect();

    $import->refresh();

    expect($import->name)->toBe('Nový název')
        ->and($import->operator_1)->toBe('Jan Salát')
        ->and($import->changed_fields)->toBeNull()
        ->and($planEvent->refresh()->name)->toBe('Nový název')
        ->and($other->refresh()->hasPendingChanges())->toBeTrue();
});

test('it shows the original text again and marks it as changed when an accepted change renames an event', function () {
    $renamed = GoogleSheetImport::factory()->changed(['name' => 'Nový název'])->create(['name' => 'Starý název']);
    $planEvent = PlanEvent::factory()->create(['google_sheet_import_id' => $renamed->id, 'name' => 'Starý název', 'show_original_text' => false]);
    $otherFieldsOnly = GoogleSheetImport::factory()->changed(['operator_1' => 'Jan Salát'])->create(['name' => 'Beze změny']);
    $unchangedEvent = PlanEvent::factory()->create(['google_sheet_import_id' => $otherFieldsOnly->id, 'name' => 'Beze změny', 'show_original_text' => false]);

    $this->actingAs($this->administrator)
        ->post(route('plan-import.changes.accept', $renamed));
    $this->post(route('plan-import.changes.accept', $otherFieldsOnly));

    expect($planEvent->refresh()->show_original_text)->toBeTrue()
        ->and($planEvent->original_text_changed)->toBeTrue()
        ->and($unchangedEvent->refresh()->show_original_text)->toBeFalse()
        ->and($unchangedEvent->original_text_changed)->toBeFalse();
});

test('it creates or removes the paired plan event when an accepted name appears or disappears', function () {
    $named = GoogleSheetImport::factory()->changed(['name' => 'Nový název'])->create(['name' => null]);
    $cleared = GoogleSheetImport::factory()->changed(['name' => null])->create(['name' => 'Starý název']);
    PlanEvent::factory()->create(['google_sheet_import_id' => $cleared->id]);

    $this->actingAs($this->administrator)
        ->post(route('plan-import.changes.accept', $named));
    $this->post(route('plan-import.changes.accept', $cleared));

    expect($named->planEvent->name)->toBe('Nový název')
        ->and(PlanEvent::where('google_sheet_import_id', $cleared->id)->exists())->toBeFalse();
});

test('it accepts the changes of every row of one import only', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    GoogleSheetImport::factory()->count(2)->changed()->create();
    $unchanged = GoogleSheetImport::factory()->create();
    $otherTab = GoogleSheetImport::factory()->changed()->create(['sheet_name' => 'Říjen']);

    $this->actingAs($this->administrator)
        ->post(route('plan-import.changes.accept-all', $setting))
        ->assertRedirect();

    expect(GoogleSheetImport::where('sheet_name', 'Listopad')->whereNotNull('changed_fields')->count())->toBe(0)
        ->and(GoogleSheetImport::where('name', 'Changed event')->count())->toBe(2)
        ->and($unchanged->refresh()->name)->not->toBe('Changed event')
        ->and($otherTab->refresh()->hasPendingChanges())->toBeTrue();
});

test('it moves to the next year when the months wrap around', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    fakeGoogle([
        ['30.12.', 'st', 'služba', 'Silvestr'],
        ['31.12.', 'čt', 'služba', 'Silvestr'],
        ['1.1.', 'pá', 'služba', 'Nový rok'],
    ]);

    $this->actingAs($this->administrator)->post(route('plan-import.run', $setting));

    expect(GoogleSheetImport::orderBy('date')->get()->map(fn (GoogleSheetImport $import) => $import->date->toDateString())->all())
        ->toBe(['2026-12-30', '2026-12-31', '2027-01-01']);
});

test('it skips blank rows and rows with an invalid or duplicate date', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    fakeGoogle([
        ['1.11.', 'ne', 'služba', 'Koncert'],
        [],
        ['', '', '', '', '', ''],
        ['32.11.', 'po', 'služba', 'Neplatné datum'],
        ['Poznámka', '', '', 'Bez data'],
        ['1.11.', 'ne', 'služba', 'Duplicitní datum'],
    ]);

    $this->actingAs($this->administrator)
        ->post(route('plan-import.run', $setting))
        ->assertSessionHasNoErrors();

    expect(GoogleSheetImport::pluck('name')->all())->toBe(['Koncert']);
});

test('it reports an import that finds no rows and records the failure', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    fakeGoogle([]);

    $this->actingAs($this->administrator)
        ->post(route('plan-import.run', $setting))
        ->assertSessionHasErrors('sheet');

    expect($setting->refresh()->last_import_status)->toBe(ImportStatus::Failed)
        ->and($setting->last_import_message)->toContain('does not contain any rows');
});

test('it reports a rejected sheet and keeps the stored rows', function () {
    $setting = GoogleSheetImportSetting::factory()->create();
    GoogleSheetImport::factory()->create();
    fakeGoogle([], status: 403);

    $this->actingAs($this->administrator)
        ->post(route('plan-import.run', $setting))
        ->assertSessionHasErrors('sheet');

    expect(GoogleSheetImport::count())->toBe(1)
        ->and($setting->refresh()->last_import_status)->toBe(ImportStatus::Failed);
});

test('it requires a tab and year before running an import', function () {
    Http::fake();
    $setting = GoogleSheetImportSetting::factory()->create(['sheet_name' => null]);

    $this->actingAs($this->administrator)
        ->post(route('plan-import.run', $setting))
        ->assertSessionHasErrors('sheet');

    Http::assertNothingSent();
});
