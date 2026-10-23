# Flow: Import from Google Sheets

Brings the monthly event schedule from a Google Sheet into the plan calendar.

|          |                                                                                                                                                               |
| -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Actor    | User with `plan-import.manage` (Administrators by default), or the scheduler                                                                                  |
| Page     | `/plan-import`                                                                                                                                                |
| Code     | `PlanImportController`, `PlanImportTabsController`, `PlanImportRunController`, `ImportGoogleSheet` command, `GoogleSheetImporter`, `GoogleSheetImportSetting` |
| Tables   | `google_sheet_import_settings`, `google_sheet_import`, `plan_events`                                                                                          |
| External | Google Sheets API v4 (`https://sheets.googleapis.com/v4`, API key in the `X-Goog-Api-Key` header)                                                             |

The flow has three stages: **configure** an import, **run** it (manually or by schedule), then **review** what changed (see [accept-import-changes.md](accept-import-changes.md)).

## 1. End-to-end overview

```mermaid
flowchart TD
    A([Planner opens /plan-import]) --> B[Fill in spreadsheet URL or id,<br/>API key optional if default exists]
    B --> C[Click load tabs]
    C --> D{Google returns tabs?}
    D -- no --> D1[Show error: check key, id, sharing] --> B
    D -- yes --> E[Pick tab, rows, year, schedule]
    E --> F[Save import]
    F --> G{Tab exists in spreadsheet?}
    G -- no --> G1[Validation error on sheet_name] --> E
    G -- yes --> H[(Setting saved,<br/>API key encrypted)]
    H --> I{How is it run?}
    I -- Run button --> J[Run import]
    I -- auto_import + cron --> K[Scheduler every minute<br/>runs only due imports] --> J
    J --> L[Fetch range A..J of the tab]
    L --> M[Parse rows, skip invalid dates]
    M --> N{Row stored already?}
    N -- no, has content --> O[Create row + paired plan event]
    N -- no, empty --> P[Ignore]
    N -- yes --> Q{Any compared field differs?}
    Q -- no --> R[Clear changed_fields]
    Q -- yes --> S[Store differences in changed_fields]
    O --> T[Record status and summary on the setting]
    R --> T
    S --> T
    P --> T
    T --> U([Review changes, accept if needed])
```

## 2. Configure an import

The form works in two steps so a wrong key, id or tab is caught before anything is saved.

```mermaid
sequenceDiagram
    autonumber
    actor P as Planner
    participant UI as plan-import page (React)
    participant T as POST /plan-import/tabs
    participant C as PlanImportController
    participant I as GoogleSheetImporter
    participant G as Google Sheets API
    participant DB as Database

    P->>UI: Paste spreadsheet URL or id (+ API key)
    UI->>T: spreadsheet_id, api_key, import_id?
    Note over T: ShowPlanImportTabsRequest extracts the id from a URL<br/>and picks key: submitted, saved, or GOOGLE_SHEETS_API_KEY
    T->>I: fetchSheetNames(apiKey, spreadsheetId)
    I->>G: GET spreadsheets/{id}?fields=sheets.properties.title
    G-->>I: tab titles
    I-->>T: list of titles
    T-->>UI: 200 JSON { sheet_names: [...] }
    P->>UI: Choose tab, first row, last row, year, auto import, cron
    UI->>C: POST /plan-import (or PUT /plan-import/{id})
    C->>I: fetchSheetNames() again
    I->>G: GET spreadsheets/{id}?fields=...
    G-->>I: tab titles
    alt chosen tab not in the list
        C-->>UI: 422 errors.sheet_name = "The tab does not exist..."
    else tab exists
        C->>DB: save setting (api_key encrypted, sheet_names cached)
        C-->>UI: redirect to /plan-import?import={id} + flash "Run it to load the rows."
    end
```

### Fields

| Field             | Rules                                                                                                                                                          | Default                        |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------ |
| `api_key`         | required only if neither the saved nor the default key exists, max 255. The key used (submitted, else saved, else default) is stored encrypted with the import | `GOOGLE_SHEETS_API_KEY`        |
| `spreadsheet_id`  | required, `[A-Za-z0-9_-]+`, a pasted `.../spreadsheets/d/<id>/...` URL is reduced to the id                                                                    | `GOOGLE_SHEETS_SPREADSHEET_ID` |
| `sheet_name`      | required, must exist in the spreadsheet                                                                                                                        | none                           |
| `first_row`       | integer 1 to 10000                                                                                                                                             | 15                             |
| `last_row`        | integer, `>= first_row`, max 10000                                                                                                                             | 40                             |
| `year`            | integer 2000 to 2100                                                                                                                                           | current year                   |
| `auto_import`     | boolean                                                                                                                                                        | false                          |
| `cron_expression` | valid cron expression, max 100                                                                                                                                 | `0 * * * *` (hourly)           |

Updating an import without sending `api_key` keeps the saved key. The import reads the sheet with the key stored on it, so changing `GOOGLE_SHEETS_API_KEY` later does not change imports that were already saved. Deleting an import removes only the setting; imported rows and plan events stay.

## 3. Run an import

### Triggers

| Trigger   | How                                                                             | Notes                                                                                        |
| --------- | ------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Manual    | `POST /plan-import/{id}/run` (Run button)                                       | Runs immediately, flashes a summary and a warning for skipped rows.                          |
| Scheduled | `Schedule::command('google-sheet:import')->everyMinute()->withoutOverlapping()` | The command runs imports with `auto_import = true` whose cron expression is due this minute. |
| CLI       | `php artisan google-sheet:import {id}`                                          | Runs one import regardless of `auto_import` and cron. Unknown id exits with failure.         |

```mermaid
sequenceDiagram
    autonumber
    participant S as Scheduler (every minute)
    participant CMD as google-sheet:import
    participant ST as GoogleSheetImportSetting
    participant I as GoogleSheetImporter
    participant G as Google Sheets API
    participant DB as Database

    S->>CMD: run (withoutOverlapping)
    CMD->>ST: auto_import = true
    loop every automatic setting
        CMD->>ST: isImportDue(now)? (valid cron and isDue)
        alt not due
            ST-->>CMD: skip
        else due
            CMD->>I: import(setting)
            I->>I: isReadyToImport? (key, spreadsheet, tab, year)
            I->>G: GET values/'Tab'!A{first}:J{last}
            alt Google error, network error or empty range
                G-->>I: error
                I->>DB: status = failed, message, last_imported_at
                I-->>CMD: ValidationException
                CMD->>CMD: print error, continue with next import
            else values returned
                G-->>I: rows
                I->>I: parseRows() then importRow() per row (one DB transaction)
                I->>DB: status = success, message "N new, N changed, N unchanged."
                I-->>CMD: result
            end
        end
    end
    CMD-->>S: exit code (failure if any import failed)
```

### Request to Google

- Endpoint: `GET /spreadsheets/{spreadsheetId}/values/{range}` with range `'<tab>'!A<first_row>:J<last_row>` (single quotes in the tab name are doubled).
- Timeouts: 3 s connect, 10 s total.
- Retries: two retries (after 200 ms and 500 ms) on connection errors, HTTP 5xx and HTTP 429. Other errors fail immediately.
- Errors are translated into a validation message such as _Google Sheets rejected the request (HTTP 403): ... Check the API key, the sheet id and that the sheet is shared with everyone who has the link._

### Column mapping

| Sheet column | Meaning                 | Stored in                             |
| ------------ | ----------------------- | ------------------------------------- |
| A            | Date, `d.m.` or `d.m.Y` | `date` (not compared)                 |
| B            | Weekday                 | `weekday`                             |
| C            | Service                 | `service`                             |
| D            | Event name              | `name` (copied to `plan_events.name`) |
| E            | Comment                 | `comment`                             |
| F            | Operator 1              | `operator_1` (text)                   |
| G            | Operator 2              | `operator_2` (text)                   |
| H            | not imported            | none                                  |
| I            | HTL technician 1        | `htl_1`                               |
| J            | HTL technician 2        | `htl_2`                               |

Cells are trimmed and empty cells become `NULL`. The operator and HTL columns are informational text from the sheet; they are not linked to user accounts (assignments to accounts are a separate flow, see [assign-event-to-user.md](assign-event-to-user.md)).

### Row parsing rules

```mermaid
flowchart TD
    R[Row from the sheet] --> E{All cells empty?}
    E -- yes --> X1[Ignore silently]
    E -- no --> D{Column A matches d.m. or d.m.Y<br/>and is a real calendar date?}
    D -- no --> X2[Skipped, row number reported]
    D -- yes --> Y{Year given in the cell?}
    Y -- yes --> Y1[Use that year from now on]
    Y -- no --> Y2{Month smaller than the previous row's month?}
    Y2 -- yes --> Y3[Year + 1<br/>sheet wraps from December to January]
    Y2 -- no --> Y4[Keep current year]
    Y1 --> U{Date already seen in this run?}
    Y3 --> U
    Y4 --> U
    U -- yes --> X3[Skipped as duplicate, row number reported]
    U -- no --> OK[Row accepted for import]
```

If no row has a valid date, the run fails with _The range ... does not contain any rows with a date._

### Per-row decision

```mermaid
flowchart TD
    A[Parsed row] --> B[Find google_sheet_import by<br/>spreadsheet_id + sheet_name + date]
    B --> C{Found?}
    C -- no --> D{Any of name, comment, operators, HTL filled?}
    D -- no --> E[Do nothing]
    D -- yes --> F[Create google_sheet_import<br/>last_checked_at = now]
    F --> G{name is set?}
    G -- yes --> H[Create plan_event for the date<br/>with the imported name]
    G -- no --> I[No plan event yet]
    C -- yes --> J[Compare weekday, service, name, comment,<br/>operator_1, operator_2, htl_1, htl_2]
    J --> K{Any difference?}
    K -- no --> L[changed_fields = null<br/>counts as unchanged]
    K -- yes --> M["changed_fields = map of field to sheet value<br/>accepted values stay untouched<br/>counts as changed"]
    L --> N[Update row_number and last_checked_at]
    M --> N
```

Consequences worth knowing:

- Existing rows are **never overwritten by an import**. Differences wait in `changed_fields` until accepted.
- A row that disappears from the sheet (or falls outside the row range) is **not** deleted and not flagged.
- A difference that is reverted in the sheet before acceptance is cleared automatically on the next run.
- Because the key is `(spreadsheet_id, tab, date)`, two import settings pointing at the same tab share the same stored rows.
- A new row without an event name is stored but does not appear in the plan calendar until a name is imported and accepted.

### Result and status

Each run stores on the setting: `last_imported_at`, `last_import_status` (`success` or `failed`) and `last_import_message` (e.g. `3 new, 1 changed, 12 unchanged.`). The page shows them next to the import and, if rows have pending changes, a changed counter.

Every run, successful or failed, also creates an email notification for the receivers of the Google Sheets import, see [email-notifications.md](email-notifications.md). A problem while creating it never fails the import.

## 4. Outcomes at a glance

| Situation                                   | Result                                                                          |
| ------------------------------------------- | ------------------------------------------------------------------------------- |
| Valid sheet, first run                      | Rows and plan events created, summary `N new, 0 changed, 0 unchanged.`          |
| Valid sheet, repeat run                     | Mostly `unchanged`, differences become pending changes                          |
| Missing key, id, tab or year on the setting | Failed: _Save the connection, the tab, the rows and the year before importing._ |
| Sheet not shared or wrong key               | Failed with the Google error message                                            |
| Google unreachable                          | Failed: _Google Sheets could not be reached. Try again later._                  |
| Rows without a date                         | Skipped, listed in a warning flash                                              |
| Cron expression not due                     | Scheduler skips the import silently                                             |
