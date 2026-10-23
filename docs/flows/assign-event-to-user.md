# Flow: Assign an event to a user

Planners staff every event with up to **3 operators** and **3 engineers**. To help them decide, staff first mark the days they have time to work, and the plan calendar shows those users per day (the first to select a day is listed first).

|           |                                                                                                                                                                                                             |
| --------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Actors    | Staff (mark availability), planners (assign), administrators (everything)                                                                                                                                   |
| Pages     | `/plan-calendar-view`, `/plan-calendar-edit`                                                                                                                                                                |
| Endpoints | `PUT /plan-calendar-view/availability`, `PUT /plan-events/{planEvent}/assignments`, `PUT /plan-calendar-edit/staff-visibility`                                                                              |
| Code      | `PlanCalendarAvailabilityController`, `UpdatePlanAvailabilityRequest`, `PlanEventAssignmentController`, `UpdatePlanEventAssignmentsRequest`, `PlanCalendarStaffVisibilityController`, `PlanCalendarBuilder` |
| Tables    | `plan_availabilities`, `plan_events`, `plan_month_settings`, `users`                                                                                                                                        |

## 1. End-to-end overview

```mermaid
flowchart TD
    subgraph Prepare
        I[Events exist<br/>imported from the sheet or added by hand]
        U[Users exist with accounts]
    end
    subgraph Staff
        S1[Staff open plan calendar view] --> S2[Select days they have time]
        S2 --> S3[(plan_availabilities<br/>created_at keeps the order)]
    end
    subgraph Planner
        P1[Planner opens plan calendar edit] --> P2[Sees per day who has time,<br/>first selector on top]
        P2 --> P3[Pick users for operator 1-3<br/>and engineer 1-3]
        P3 --> P4{Validation passes?}
        P4 -- no --> P5[Errors on the offending position] --> P3
        P4 -- yes --> P6[(plan_events assignment columns)]
        P6 --> P7[Optional: show staff for the month]
    end
    I --> P1
    U --> S1
    U --> P3
    S3 --> P2
    P7 --> V[Staff see who works on which event]
```

## 2. Step 1: staff mark the days they have time

```mermaid
sequenceDiagram
    autonumber
    actor U as Staff member
    participant UI as plan-calendar-view page
    participant C as PlanCalendarAvailabilityController
    participant R as UpdatePlanAvailabilityRequest
    participant DB as Database

    U->>UI: Click a day of next month
    UI->>R: PUT /plan-calendar-view/availability { date, available: true }
    R->>R: date is Y-m-d and on or after the first day of next month
    alt date in the current or a past month
        R-->>UI: errors.date
    else valid
        R->>C: validated
        alt available = true
            C->>DB: exists for (user, date)?
            Note over C,DB: Already selected: nothing changes,<br/>the original created_at (queue position) is kept
            C->>DB: otherwise INSERT plan_availability
        else available = false
            C->>DB: DELETE the logged-in user's row for the date
        end
        C-->>UI: redirect back
    end
```

Rules:

- Requires `plan-calendar.view`. A user always changes **only their own** days.
- Only days from the first day of the **next month** onwards can be changed. The current month is already being planned.
- One row per user and date (unique index). Deleting a user deletes their days.
- The selection time is the queue: `PlanCalendarBuilder` orders `availableUserIds` by `created_at`, so the earliest selector is listed first.

## 3. Step 2: the planner assigns users to an event

```mermaid
sequenceDiagram
    autonumber
    actor P as Planner
    participant UI as plan-calendar-edit page
    participant MW as Middleware (auth, can:plan-calendar.edit)
    participant R as UpdatePlanEventAssignmentsRequest
    participant C as PlanEventAssignmentController
    participant DB as Database

    P->>UI: Open /plan-calendar-edit?month=2026-11
    UI->>MW: GET
    MW-->>UI: days[] with events and availableUserIds, categories[], staff[]
    P->>UI: Select users for the positions of one event
    UI->>MW: PUT /plan-events/{planEvent}/assignments<br/>operator_1..3, engineer_1..3 (null clears a position)
    MW->>R: validate
    R->>R: 1. all six keys present, each null or an existing user id
    R->>R: 2. no user twice within the event
    R->>DB: 3. is any chosen user on another event on the same date?
    R->>R: 4. positions filled in order (2 needs 1, 3 needs 2)
    alt any check fails
        R-->>UI: errors keyed by the position, e.g. engineer_2
    else all pass
        R->>C: validated
        C->>DB: UPDATE plan_events SET operator_1..3, engineer_1..3
        C-->>UI: redirect back
    end
```

### Validation (`UpdatePlanEventAssignmentsRequest`)

| Check                                                         | Error message                                                     |
| ------------------------------------------------------------- | ----------------------------------------------------------------- |
| Every position key must be sent (`present`), `null` clears it | validation "must be present"                                      |
| Value is a user id that exists                                | `exists:users,id`                                                 |
| A user can hold only one position of one event                | _A user can be assigned to only one position of an event._        |
| A user cannot be on two events of the same day                | _This user is already assigned to another event on the same day._ |
| Operators and engineers fill from the first position          | _Select the previous position first._                             |

The same user may work on events of **different** days, and may be freed for another event of the day by unassigning them first. The availability list is a hint only: assigning a user who did not select the day is allowed.

### Positions

| Group     | Columns                                  | Max |
| --------- | ---------------------------------------- | --- |
| Operators | `operator_1`, `operator_2`, `operator_3` | 3   |
| Engineers | `engineer_1`, `engineer_2`, `engineer_3` | 3   |

Each column is a foreign key to `users.id` with `nullOnDelete`: when a user account is deleted, their positions become empty.

> Do not confuse these with the `operator_1`, `operator_2`, `htl_1`, `htl_2` columns of `google_sheet_import`. Those are free text copied from the sheet and are not linked to accounts.

## 4. Step 3: who is allowed to see the staff

Operators and engineers are sensitive until the plan is final, so each month has a switch.

```mermaid
flowchart TD
    Q[Build month payload] --> M{revealStaff?<br/>true on the edit page}
    M -- yes --> SHOW[Include assignments]
    M -- no --> S{plan_month_settings.staff_visible<br/>for this month}
    S -- true --> SHOW
    S -- false or no row --> HIDE[All six assignments returned as null]
```

```mermaid
sequenceDiagram
    autonumber
    actor P as Planner
    participant UI as plan-calendar-edit page
    participant C as PlanCalendarStaffVisibilityController
    participant DB as Database
    actor V as Viewer

    P->>UI: Toggle "show staff" for the month
    UI->>C: PUT /plan-calendar-edit/staff-visibility { month: "2026-11", visible: true }
    C->>DB: upsert plan_month_settings (month, staff_visible)
    C-->>UI: redirect back
    V->>V: Opens /plan-calendar-view?month=2026-11
    Note over V: Assignments are included only while the month is visible
```

- The planner always sees assignments on the edit page.
- The plan calendar view hides them until the month is switched on. A month without a setting is hidden.

## 5. Complete timeline for one event

```mermaid
sequenceDiagram
    autonumber
    participant G as Google Sheet
    participant S as System
    actor St as Staff
    actor P as Planner
    actor V as Viewer

    G->>S: Import creates event "Koncert" on 2026-11-12
    St->>S: Mark 2026-11-12 as available (queue by time)
    P->>S: Open edit page, sees who has time and in which order
    P->>S: PUT assignments: operator_1 = Ada, operator_2 = Bob, engineer_1 = Eve
    S-->>P: Saved
    P->>S: Add objects with categories to the event
    P->>S: Show staff for 2026-11
    V->>S: Open plan calendar view
    S-->>V: Event with chips and assigned staff
    Note over G,S: Later the sheet name changes: the event keeps its assignments<br/>until the change is accepted (see accept-import-changes.md)
```

## 6. Edge cases

| Case                                                     | Behaviour                                           |
| -------------------------------------------------------- | --------------------------------------------------- |
| Assign a user to both operator and engineer of one event | Rejected, one position per event.                   |
| Assign a user already on another event the same day      | Rejected on that position.                          |
| Fill `operator_3` while `operator_2` is empty            | Rejected: _Select the previous position first._     |
| Omit a position key in the request                       | Validation error, send `null` to clear.             |
| Assigned user deleted                                    | The position becomes empty, the event stays.        |
| Accepted import change renames the event                 | Assignments are kept.                               |
| Accepted import change empties the name                  | The event, its objects and assignments are deleted. |
| User without `plan-calendar.edit`                        | 403 on the assignment and visibility endpoints.     |
| Mark availability for the current month                  | Rejected, only following months are open.           |
