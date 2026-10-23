# HTTP API reference

Machine-readable specification: [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1). Interactive view: [`swagger.html`](swagger.html).

```bash
php -S localhost:8081 -t docs
# open http://localhost:8081/swagger.html
```

## Overview

The application has **no separate REST or token API**. Every endpoint is a session-authenticated web route of a Laravel + Inertia application:

| Kind     | Methods                          | Response                                                                        |
| -------- | -------------------------------- | ------------------------------------------------------------------------------- |
| Page     | `GET`                            | Inertia page: HTML document, or JSON page object when `X-Inertia: true` is sent |
| Mutation | `POST`, `PUT`, `PATCH`, `DELETE` | Redirect back (`302`, `303` for the Inertia client) with a flash message        |
| JSON     | `POST /plan-import/tabs`         | `200` JSON, the only endpoint that always returns JSON                          |

Base URL in development: `http://localhost:8000`.

## Authentication

1. `GET /login` sets the session and `XSRF-TOKEN` cookies.
2. `POST /login` with `email`, `password` (and optional `remember`) signs the user in and regenerates the session id.
3. Send the session cookie on every request. Send the value of the `XSRF-TOKEN` cookie in the `X-XSRF-TOKEN` header on every non-GET request.
4. `POST /logout` ends the session.

Rate limit: 5 failed logins per email and IP, then a throttle message until the lockout expires.

```bash
# 1. get cookies
curl -c jar.txt http://localhost:8000/login -o /dev/null

# 2. read the CSRF token (URL-decoded) from the cookie jar
TOKEN=$(awk '$6=="XSRF-TOKEN"{print $7}' jar.txt | python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')

# 3. log in
curl -b jar.txt -c jar.txt -X POST http://localhost:8000/login \
  -H "X-XSRF-TOKEN: $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"michal.virt@gmail.com","password":"password"}'

# refresh the token from the updated jar before the next mutation
```

## Authorization

Routes are protected by `auth` and a `can:<permission>` gate.

| Permission                   | Endpoints                                                         |
| ---------------------------- | ----------------------------------------------------------------- |
| none (public)                | `/`, `/up`                                                        |
| guest only                   | `GET/POST /login`                                                 |
| authenticated                | `POST /logout`                                                    |
| `calendar.view`              | `GET /calendar`                                                   |
| `plan-calendar.view`         | `GET /plan-calendar-view`, `PUT /plan-calendar-view/availability` |
| `plan-calendar.edit`         | `/plan-calendar-edit*`, `/plan-events*`, `/plan-event-objects*`   |
| `plan-categories.manage`     | `/plan-categories*`                                               |
| `plan-import.manage`         | `/plan-import*`                                                   |
| `email-notifications.manage` | `/email-notifications*`                                           |
| `accounts.manage`            | `/accounts*`, `/permission-groups*`                               |

Two more permissions do not protect an endpoint, they only make a user receive an email notification: `importGoogleSheet.notification` and `planEventChange.notification`. They can be set on a group or on a single account (`/accounts`).

Default groups: **Administrators** (all nine permissions), **Planners** (`calendar.view`, `plan-calendar.view`, `plan-calendar.edit`, `planEventChange.notification`), **Viewers** (`calendar.view`, `plan-calendar.view`, `planEventChange.notification`).

## Conventions

| Topic           | Rule                                                                                |
| --------------- | ----------------------------------------------------------------------------------- |
| Identifiers     | UUIDs                                                                               |
| Dates / months  | `YYYY-MM-DD` / `YYYY-MM`                                                            |
| Request bodies  | JSON (`application/json`) or form data                                              |
| `PUT`           | Resource routes also accept `PATCH`                                                 |
| Unauthenticated | Redirect to `/login`, `401` for JSON requests                                       |
| Forbidden       | `403` (missing permission, or a business rule such as deleting your own account)    |
| Not found       | `404` for unknown ids                                                               |
| Validation      | Browser: redirect back with `errors` prop. JSON (`Accept: application/json`): `422` |

Validation error body:

```json
{
    "message": "The email field is required.",
    "errors": { "email": ["The email field is required."] }
}
```

### Inertia page object

Send `X-Inertia: true` (and optionally `X-Inertia-Version`) on a `GET`:

```json
{
    "component": "plan-categories",
    "props": {
        "name": "Laravel",
        "auth": {
            "user": {
                "id": "…",
                "name": "Ada",
                "surname": "Admin",
                "email": "ada@example.com",
                "phone": null,
                "permission_group": "Administrators",
                "permissions": ["accounts.manage"]
            }
        },
        "errors": {},
        "categories": []
    },
    "flash": { "success": "Category Koncert was created." },
    "url": "/plan-categories",
    "version": "…"
}
```

Shared props on every page: `name`, `auth.user` (`null` for guests) and `errors`. Flash data (`success`, `warning`) arrives on the page loaded after a mutation.

## Endpoint index

| Method | Path                                                            | Name                                                | Permission                   | Summary                                     |
| ------ | --------------------------------------------------------------- | --------------------------------------------------- | ---------------------------- | ------------------------------------------- |
| GET    | `/up`                                                           | none                                                | none                         | Health check                                |
| GET    | `/`                                                             | `home`                                              | none                         | Home page                                   |
| GET    | `/login`                                                        | `login`                                             | guest                        | Login form                                  |
| POST   | `/login`                                                        | `login.store`                                       | guest                        | Sign in                                     |
| POST   | `/logout`                                                       | `logout`                                            | auth                         | Sign out                                    |
| PUT    | `/profile`                                                      | `profile.update`                                    | auth                         | Update your own profile                     |
| PUT    | `/profile/password`                                             | `profile.password.update`                           | auth                         | Change your own password                    |
| GET    | `/calendar`                                                     | `calendar`                                          | `calendar.view`              | Month calendar                              |
| GET    | `/plan-calendar-view`                                           | `plan-calendar-view`                                | `plan-calendar.view`         | Plan of a month (read-only)                 |
| PUT    | `/plan-calendar-view/availability`                              | `plan-calendar-view.availability`                   | `plan-calendar.view`         | Mark or clear an available day              |
| POST   | `/plan-events/{planEvent}/change-acknowledgement`               | `plan-events.change-acknowledgement`                | `plan-calendar.view`         | Assigned user confirms an accepted change   |
| GET    | `/plan-calendar-edit`                                           | `plan-calendar-edit`                                | `plan-calendar.edit`         | Plan editor                                 |
| PUT    | `/plan-calendar-edit/staff-visibility`                          | `plan-calendar-edit.staff-visibility`               | `plan-calendar.edit`         | Show or hide staff for a month              |
| POST   | `/plan-events`                                                  | `plan-events.store`                                 | `plan-calendar.edit`         | Add an event to a day                       |
| PUT    | `/plan-events/{planEvent}`                                      | `plan-events.update`                                | `plan-calendar.edit`         | Show or hide original text                  |
| DELETE | `/plan-events/{planEvent}`                                      | `plan-events.destroy`                               | `plan-calendar.edit`         | Remove a manual event                       |
| PUT    | `/plan-events/{planEvent}/assignments`                          | `plan-events.assignments`                           | `plan-calendar.edit`         | Assign operators and engineers              |
| PUT    | `/plan-events/{planEvent}/objects/order`                        | `plan-events.objects.order`                         | `plan-calendar.edit`         | Reorder objects                             |
| POST   | `/plan-event-objects`                                           | `plan-event-objects.store`                          | `plan-calendar.edit`         | Add an object                               |
| PUT    | `/plan-event-objects/{planEventObject}`                         | `plan-event-objects.update`                         | `plan-calendar.edit`         | Change an object                            |
| DELETE | `/plan-event-objects/{planEventObject}`                         | `plan-event-objects.destroy`                        | `plan-calendar.edit`         | Remove an object                            |
| GET    | `/plan-categories`                                              | `plan-categories.index`                             | `plan-categories.manage`     | List categories                             |
| POST   | `/plan-categories`                                              | `plan-categories.store`                             | `plan-categories.manage`     | Create a category                           |
| PUT    | `/plan-categories/{planEventCategory}`                          | `plan-categories.update`                            | `plan-categories.manage`     | Update a category                           |
| DELETE | `/plan-categories/{planEventCategory}`                          | `plan-categories.destroy`                           | `plan-categories.manage`     | Delete an unused category                   |
| GET    | `/plan-import`                                                  | `plan-import.index`                                 | `plan-import.manage`         | Imports and rows                            |
| POST   | `/plan-import`                                                  | `plan-import.store`                                 | `plan-import.manage`         | Save an import                              |
| POST   | `/plan-import/tabs`                                             | `plan-import.tabs`                                  | `plan-import.manage`         | List tabs of a spreadsheet (JSON)           |
| PUT    | `/plan-import/{googleSheetImportSetting}`                       | `plan-import.update`                                | `plan-import.manage`         | Update an import                            |
| DELETE | `/plan-import/{googleSheetImportSetting}`                       | `plan-import.destroy`                               | `plan-import.manage`         | Delete an import                            |
| POST   | `/plan-import/{googleSheetImportSetting}/run`                   | `plan-import.run`                                   | `plan-import.manage`         | Run an import now                           |
| POST   | `/plan-import/{googleSheetImportSetting}/changes/accept`        | `plan-import.changes.accept-all`                    | `plan-import.manage`         | Accept all pending changes                  |
| POST   | `/plan-import/changes/{googleSheetImport}/accept`               | `plan-import.changes.accept`                        | `plan-import.manage`         | Accept the changes of one row               |
| GET    | `/email-notifications?state=&page=`                             | `email-notifications.index`                         | `email-notifications.manage` | Notification log and settings               |
| PUT    | `/email-notifications/actions/{emailNotificationActionSetting}` | `email-notifications.actions.update`                | `email-notifications.manage` | Save the settings of one notification       |
| POST   | `/email-notifications/send-pending`                             | `email-notifications.send-pending`                  | `email-notifications.manage` | Send every waiting notification             |
| POST   | `/email-notifications/send-failed`                              | `email-notifications.send-failed`                   | `email-notifications.manage` | Send every failed notification again        |
| POST   | `/email-notifications/{emailNotificationLog}/send`              | `email-notifications.send`                          | `email-notifications.manage` | Send or resend one notification             |
| DELETE | `/email-notifications/{emailNotificationLog}`                   | `email-notifications.destroy`                       | `email-notifications.manage` | Delete a log row                            |
| GET    | `/accounts`                                                     | `accounts.index`                                    | `accounts.manage`            | List accounts                               |
| POST   | `/accounts`                                                     | `accounts.store`                                    | `accounts.manage`            | Create an account                           |
| PUT    | `/accounts/{account}`                                           | `accounts.update`                                   | `accounts.manage`            | Update an account                           |
| DELETE | `/accounts/{account}`                                           | `accounts.destroy`                                  | `accounts.manage`            | Delete an account                           |
| PUT    | `/accounts/{account}/password`                                  | `accounts.password.update`                          | `accounts.manage`            | Reset a password                            |
| PUT    | `/permission-groups/{permissionGroup}/notification-permissions` | `permission-groups.notification-permissions.update` | `accounts.manage`            | Set the notification permissions of a group |

## Endpoints in detail

### Authentication

#### `POST /login`

| Field      | Type    | Rules           |
| ---------- | ------- | --------------- |
| `email`    | string  | required, email |
| `password` | string  | required        |
| `remember` | boolean | optional        |

Redirects to the intended URL, else `/`. Wrong credentials: error on `email`. Too many attempts: error on `email` with the seconds left.

#### `POST /logout`

No body. Redirects to `/`.

### Profile

Every signed-in user can change their own account on the home page (`/`), no permission is needed.

#### `PUT /profile`

| Field     | Rules                                                     |
| --------- | --------------------------------------------------------- |
| `name`    | required, string, max 255                                 |
| `surname` | required, string, max 255                                 |
| `email`   | required, email, max 255, unique among the other accounts |
| `phone`   | optional, string, max 255                                 |

Updates the signed-in user only. The permission group and permissions cannot be changed here.

#### `PUT /profile/password`

| Field              | Rules                                                                 |
| ------------------ | --------------------------------------------------------------------- |
| `current_password` | required, must match the current password                             |
| `password`         | required, confirmed (`password_confirmation`), `Password::defaults()` |

Rotates the remember token and signs the user out of the other sessions (database session driver), the current session stays.

### Calendar

#### `GET /calendar?month=YYYY-MM`

Props: `month`, `monthLabel`, `previousMonth`, `nextMonth`, `currentMonth`, `weekdays[]` (Monday first), `weeks[][]` of `{ date, day, isCurrentMonth, isToday }`. An invalid `month` returns a validation error.

### Plan calendar

#### `GET /plan-calendar-view?month=YYYY-MM` and `GET /plan-calendar-edit?month=YYYY-MM`

Props:

| Prop                                                                | Description                                                                                                                                                                                    |
| ------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `month`, `monthLabel`, `previousMonth`, `nextMonth`, `currentMonth` | Navigation                                                                                                                                                                                     |
| `staffVisible`                                                      | Whether the month's staff is shown in the view                                                                                                                                                 |
| `days[]`                                                            | `date`, `day`, `weekday`, `isWeekend`, `isToday`, `availableUserIds[]` (first selector first), `events[]`                                                                                      |
| `days[].events[]`                                                   | `id`, `name`, `showOriginalText`, `originalTextChanged`, `awaitsChangeAcknowledgement`, `isImported`, `hasChanges`, `changedFields[]`, `changedName`, `assignments` (6 positions), `objects[]` |
| `days[].events[].objects[]`                                         | `id`, `text`, `plan_event_category_id`, `category` (`{ id, label, background_color, font_color }`), in the saved order                                                                         |
| `staff[]`                                                           | `{ id, name, surname }` of every user                                                                                                                                                          |
| `categories[]`                                                      | Edit page only: `{ id, label, background_color, font_color }`                                                                                                                                  |

In the view, `assignments` are all `null` unless the month is made visible. The edit page always includes them.

`awaitsChangeAcknowledgement` is `true` only on the view page, only for a user assigned to the event, while `originalTextChanged` is `true` and that user has not confirmed the change yet. It is independent of the month's staff visibility. The page shows the orange row and the confirm button for such events only.

#### `POST /plan-events/{planEvent}/change-acknowledgement`

No body. The logged-in user confirms that they know about the accepted change of the event. `403` when the user holds none of the six positions of the event; an event whose `original_text_changed` is `false` is ignored. Other users' confirmations and `original_text_changed` are untouched.

#### `PUT /plan-calendar-view/availability`

| Field       | Rules                                                      |
| ----------- | ---------------------------------------------------------- |
| `date`      | required, `Y-m-d`, on or after the first day of next month |
| `available` | required, boolean (`true` selects, `false` clears)         |

Affects only the logged-in user. Re-selecting a selected day keeps its original timestamp.

#### `PUT /plan-calendar-edit/staff-visibility`

| Field     | Rules             |
| --------- | ----------------- |
| `month`   | required, `Y-m`   |
| `visible` | required, boolean |

### Plan events

#### `POST /plan-events`

`date` (required, `Y-m-d`). Creates an event without name and import.

#### `PUT /plan-events/{planEvent}`

`show_original_text` (required, boolean). Also sets `original_text_changed = false` and forgets the confirmations of the assigned users.

#### `DELETE /plan-events/{planEvent}`

Deletes a manual event and its objects. Imported events: `403` _An imported event cannot be removed._

#### `PUT /plan-events/{planEvent}/assignments`

All six keys must be present: `operator_1..3`, `engineer_1..3`, each a user id or `null`.

```json
{
    "operator_1": "0b0c7a8e-2f64-4c43-9a3b-2d6c4b2f8e11",
    "operator_2": null,
    "operator_3": null,
    "engineer_1": "5d1d7f36-9c3e-4f07-8f86-5a1c2e9b7a42",
    "engineer_2": null,
    "engineer_3": null
}
```

Errors (keyed by the position):

- _A user can be assigned to only one position of an event._
- _This user is already assigned to another event on the same day._
- _Select the previous position first._

#### `PUT /plan-events/{planEvent}/objects/order`

`objects`: array of every object id of the event, each exactly once, in the new order.

#### `POST /plan-event-objects`, `PUT /plan-event-objects/{planEventObject}`

| Field                    | Rules                                             |
| ------------------------ | ------------------------------------------------- |
| `plan_event_id`          | required on create, must exist, ignored on update |
| `plan_event_category_id` | required, must exist                              |
| `text`                   | required, max 255                                 |

New objects are appended after the existing ones. `DELETE /plan-event-objects/{planEventObject}` removes one.

### Plan categories

#### `GET /plan-categories`

Props: `categories[]` of `{ id, label, background_color, font_color, objectsCount }`, ordered by label.

#### `POST /plan-categories`, `PUT /plan-categories/{planEventCategory}`

| Field              | Rules                                                  |
| ------------------ | ------------------------------------------------------ |
| `label`            | required, max 100, unique (an update may keep its own) |
| `background_color` | required, `#RRGGBB`                                    |
| `font_color`       | required, `#RRGGBB`                                    |

#### `DELETE /plan-categories/{planEventCategory}`

Validation error keyed `category` while objects use the category: _Category X is used by N objects. Remove or change them first._

### Google Sheets import

#### `GET /plan-import?import={id}`

Props: `imports[]` (settings without the API key, with `hasApiKey` and `changedCount`), `selectedId`, `rows[]` of the selected import (with `changed_fields` and `hasChanges`) and `defaults`.

#### `POST /plan-import/tabs`

The only JSON endpoint.

Request:

```json
{
    "spreadsheet_id": "https://docs.google.com/spreadsheets/d/1AbC.../edit",
    "api_key": "AIza...",
    "import_id": null
}
```

Response `200`:

```json
{ "sheet_names": ["Listopad 2026", "Prosinec 2026"] }
```

`api_key` falls back to the key of `import_id`, then to `GOOGLE_SHEETS_API_KEY`. Google errors come back as `422` with a message on `sheet`.

#### `POST /plan-import`, `PUT /plan-import/{googleSheetImportSetting}`

| Field             | Rules                                                          |
| ----------------- | -------------------------------------------------------------- |
| `api_key`         | required only when no saved or default key exists, max 255     |
| `spreadsheet_id`  | required, `[A-Za-z0-9_-]+` (a pasted URL is reduced to the id) |
| `sheet_name`      | required, must be a tab of the spreadsheet                     |
| `first_row`       | required, 1 to 10000                                           |
| `last_row`        | required, `>= first_row`, max 10000                            |
| `year`            | required, 2000 to 2100                                         |
| `auto_import`     | optional, boolean                                              |
| `cron_expression` | required, valid cron, max 100                                  |

The connection and tab are verified against Google before saving. The key that was used (submitted, else the saved one, else `GOOGLE_SHEETS_API_KEY`) is stored encrypted with the import. Redirects to `/plan-import?import={id}`.

#### `DELETE /plan-import/{googleSheetImportSetting}`

Deletes the setting only; imported rows and plan events are kept. Redirects to `/plan-import`.

#### `POST /plan-import/{googleSheetImportSetting}/run`

Runs the import and redirects to `/plan-import?import={id}`. Flash `success`: `Import finished: N new, N changed, N unchanged.` Flash `warning`: `Skipped rows without a valid or unique date: 17, 18.` (only when rows were skipped). Failures return a validation error keyed `sheet` and mark the import `failed`. Every run also creates the email notification of the Google Sheets import, see [email-notifications](flows/email-notifications.md).

#### `POST /plan-import/changes/{googleSheetImport}/accept` and `POST /plan-import/{googleSheetImportSetting}/changes/accept`

No body. Accept the pending changes of one row, or of every row of the import, and redirect back. Flash `success`: `Changes of YYYY-MM-DD were accepted.` or `Accepted changes of N rows.` See [accept-import-changes](flows/accept-import-changes.md).

### Email notifications

#### `GET /email-notifications?state=all|sent|failed|pending&page=1`

Inertia page `email-notifications`. An unknown `state` means `all`.

| Prop               | Description                                                                                                                                                                                                |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `notifications[]`  | 20 log rows per page, newest first: `id`, `action`, `receiver_email`, `subject`, `body`, `language`, `state` (`sent`, `failed` or `pending`), `attempts`, `error_message`, `created_at`, `last_attempt_at` |
| `pagination`       | `{ page, perPage, total }`                                                                                                                                                                                 |
| `state`            | The applied filter: `all`, `sent`, `failed` or `pending`                                                                                                                                                   |
| `counts`           | `{ sent, failed, pending }` over the whole log                                                                                                                                                             |
| `actionSettings[]` | One per notification: `id`, `action`, `label`, `background_color`, `font_color`, `send_mode`, `language`, `batch_interval_minutes`, `last_batch_sent_at`, `receiver_permission`, `is_enabled`              |
| `options`          | `{ sendModes[], languages[], permissions[] }`, the values accepted by the settings form                                                                                                                    |

#### `PUT /email-notifications/actions/{emailNotificationActionSetting}`

| Field                            | Rules                             |
| -------------------------------- | --------------------------------- |
| `label`                          | required, string, max 100         |
| `background_color`, `font_color` | required, hex color `#rrggbb`     |
| `send_mode`                      | required, `immediate` or `batch`  |
| `language`                       | required, `en` or `cs`            |
| `batch_interval_minutes`         | required, integer 1 to 1440       |
| `receiver_permission`            | required, a value of `Permission` |
| `is_enabled`                     | required, boolean                 |

#### `POST /email-notifications/{emailNotificationLog}/send`, `POST /email-notifications/send-pending`, `POST /email-notifications/send-failed`

No body. Send one row, every waiting row or every failed row again. Flash `success` with the result, or `warning` when a single send fails or the row was already sent.

#### `DELETE /email-notifications/{emailNotificationLog}`

Deletes a log row. See [email-notifications](flows/email-notifications.md).

### Accounts

#### `GET /accounts`

Props: `accounts[]` of `{ id, name, surname, email, phone, permission_group_id, permission_group, notification_permissions, isCurrentUser }`, `permissionGroups[]` of `{ id, name, notification_permissions }` and `notificationPermissions[]` of `{ value, label }`. `notification_permissions` lists the `*.notification` permissions set on the account or group itself.

#### `POST /accounts`

| Field                 | Rules                                                                                                                                |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `name`, `surname`     | required, max 255                                                                                                                    |
| `email`               | required, email, max 255, unique                                                                                                     |
| `phone`               | optional, max 255                                                                                                                    |
| `permission_group_id` | optional, must exist                                                                                                                 |
| `permissions`         | optional list of notification permissions (values ending with `.notification`) set on the account, no duplicates. Missing means none |
| `password`            | required, confirmed (`password_confirmation`), `Password::defaults()`                                                                |

#### `PUT /accounts/{account}`

Same fields without the password. `email` stays unique among the other accounts. A missing `permissions` removes the account's own notification permissions.

#### `PUT /permission-groups/{permissionGroup}/notification-permissions`

| Field         | Rules                                                                                         |
| ------------- | --------------------------------------------------------------------------------------------- |
| `permissions` | list of notification permissions, no duplicates. Missing or empty removes them from the group |

Replaces only the notification permissions of the group, its other permissions stay. A user with the permission both on the account and in the group receives one notification.

#### `PUT /accounts/{account}/password`

`password` and `password_confirmation`. Signs the account out of its other sessions (database session driver).

#### `DELETE /accounts/{account}`

`403` when deleting your own account.

## Console interface

Not HTTP, but part of the integration surface:

| Command                                          | Description                                                                                                                                                                      |
| ------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php artisan google-sheet:import`                | Runs every automatic import that is due now. Exits with a non-zero code when an import fails                                                                                     |
| `php artisan google-sheet:import {id}`           | Runs one saved import now. Exits with a non-zero code when the id does not exist or the import fails                                                                             |
| `php artisan email-notifications:send [--force]` | Sends the waiting email notifications. Immediate notifications are always sent, batch notifications only when their interval has passed, unless `--force`. Always exits with `0` |
| `php artisan schedule:run`                       | Runs the scheduler (needed once a minute in production)                                                                                                                          |

## External API used

| Service              | Calls                                                                                                                                                                                                                        |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Google Sheets API v4 | `GET /spreadsheets/{id}?fields=sheets.properties.title` (tabs), `GET /spreadsheets/{id}/values/{range}` (rows). API key in `X-Goog-Api-Key`, 3 s connect and 10 s total timeout, two retries on network errors, 5xx and 429. |

## Related flows

- [Google Sheets import](flows/google-sheets-import.md)
- [Accept changes between imports](flows/accept-import-changes.md)
- [Email notifications](flows/email-notifications.md)
- [Create a user](flows/create-user.md)
- [Create categories](flows/create-categories.md)
- [Assign an event to a user](flows/assign-event-to-user.md)
