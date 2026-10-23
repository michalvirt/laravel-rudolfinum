# Flow: Create categories

Categories give a label and a colour pair to the **objects** planners attach to a plan event (for example a concert, a rehearsal or an installation). An object is a short text plus a category, shown as a coloured chip behind the event name.

|           |                                                                                                                                                                                   |
| --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Actor     | Categories: user with `plan-categories.manage`. Objects: user with `plan-calendar.edit`                                                                                           |
| Pages     | `/plan-categories`, `/plan-calendar-edit`                                                                                                                                         |
| Endpoints | `GET/POST /plan-categories`, `PUT /plan-categories/{id}`, `DELETE /plan-categories/{id}`, `POST/PUT/DELETE /plan-event-objects...`, `PUT /plan-events/{id}/objects/order`         |
| Code      | `PlanEventCategoryController`, `SavePlanEventCategoryRequest`, `PlanEventObjectController`, `SavePlanEventObjectRequest`, `PlanEventObjectOrderController`, `PlanCalendarBuilder` |
| Tables    | `plan_event_categories`, `plan_event_objects`                                                                                                                                     |

## 1. Create a category

```mermaid
sequenceDiagram
    autonumber
    actor A as Administrator
    participant UI as plan-categories page
    participant MW as Middleware (auth, can:plan-categories.manage)
    participant R as SavePlanEventCategoryRequest
    participant C as PlanEventCategoryController
    participant DB as Database

    A->>UI: Open /plan-categories
    UI->>MW: GET /plan-categories
    MW->>C: index()
    C->>DB: categories with objects_count, ordered by label
    C-->>UI: props categories[] (id, label, colours, objectsCount)
    A->>UI: Enter label, pick background and font colour
    UI->>MW: POST /plan-categories
    MW->>R: validate
    alt invalid or label taken
        R-->>UI: errors on label / colours
    else valid
        R->>C: validated
        C->>DB: INSERT category
        C-->>UI: redirect back + flash "Category Koncert was created."
    end
```

### Validation (`SavePlanEventCategoryRequest`)

| Field              | Rules                                                                                 |
| ------------------ | ------------------------------------------------------------------------------------- |
| `label`            | required, string, max 100, unique among categories (an update may keep its own label) |
| `background_color` | required, hex colour `#RRGGBB`                                                        |
| `font_color`       | required, hex colour `#RRGGBB`                                                        |

Seeded defaults (`PlanEventCategorySeeder`, idempotent by label):

| Label     | Background | Font      |
| --------- | ---------- | --------- |
| Koncert   | `#1565c0`  | `#ffffff` |
| Zkouška   | `#2e7d32`  | `#ffffff` |
| Instalace | `#ef6c00`  | `#000000` |

## 2. Edit and delete

```mermaid
flowchart TD
    L[/Categories list/] --> E[Edit]
    L --> D[Delete]
    E --> E1[PUT /plan-categories/id]
    E1 --> E2[Validate, label unique except itself]
    E2 --> E3[Update label and colours<br/>every object using it changes look immediately]
    D --> D1[DELETE /plan-categories/id]
    D1 --> D2{objectsCount > 0?}
    D2 -- yes --> D3[Validation error on category:<br/>used by N objects, remove or change them first]
    D2 -- no --> D4[Delete]
    D4 --> D5[Flash: Category was deleted]
```

The database also protects this rule: `plan_event_objects.plan_event_category_id` is `restrictOnDelete`.

## 3. Using a category: add objects to an event

Categories are only useful once they are used on events. Planners open `/plan-calendar-edit`, which loads every day of a month with its events, the list of categories and the list of staff.

```mermaid
sequenceDiagram
    autonumber
    actor P as Planner
    participant UI as plan-calendar-edit page
    participant E as PlanEventController
    participant O as PlanEventObjectController
    participant OO as PlanEventObjectOrderController
    participant DB as Database

    P->>UI: Open /plan-calendar-edit?month=2026-11
    opt the day has no event yet
        P->>UI: Add event to the day
        UI->>E: POST /plan-events { date }
        E->>DB: INSERT plan_event (date only, no name, no import)
        E-->>UI: flash "Event was added."
    end
    P->>UI: Choose category, type text
    UI->>O: POST /plan-event-objects { plan_event_id, plan_event_category_id, text }
    O->>DB: position = max(position) + 1 (or 0)
    O->>DB: INSERT plan_event_object
    O-->>UI: flash "Object was added."
    P->>UI: Drag chips to reorder
    UI->>OO: PUT /plan-events/{id}/objects/order { objects: [ids] }
    Note over OO: Request must list every object of the event exactly once
    OO->>DB: UPDATE position = index, inside a transaction
    OO-->>UI: redirect back
    P->>UI: Change text or category, or remove the object
    UI->>O: PUT or DELETE /plan-event-objects/{id}
    O-->>UI: flash "Object was updated." / "Object was removed."
```

### Object validation (`SavePlanEventObjectRequest`)

| Field                    | Rules                                                                                             |
| ------------------------ | ------------------------------------------------------------------------------------------------- |
| `plan_event_id`          | required on create, must exist. Ignored on update, so an object cannot be moved to another event. |
| `plan_event_category_id` | required, must exist                                                                              |
| `text`                   | required, string, max 255                                                                         |

## 4. How it ends up in the plan calendar

```mermaid
flowchart LR
    CAT[(Category<br/>label + colours)] --> OBJ[(Object<br/>text + position)]
    EV[(Plan event)] --> OBJ
    OBJ --> B[PlanCalendarBuilder<br/>eager loads objects.category]
    B --> V[/plan-calendar-view<br/>read-only chips/]
    B --> ED[/plan-calendar-edit<br/>editable chips/]
    EV -->|show_original_text| T{Show imported name?}
    T -- yes --> N[Name followed by the chips]
    T -- no --> M[Only the chips]
```

Objects are sorted by `position`, then `created_at`, then `id`. The category payload sent to the frontend is `{ id, label, background_color, font_color }`.

## 5. Edge cases

| Case                                                    | Behaviour                                                          |
| ------------------------------------------------------- | ------------------------------------------------------------------ |
| Duplicate label (case sensitivity follows the database) | Validation error, nothing created.                                 |
| Colour not in `#RRGGBB` form (for example `#fff`)       | Validation error.                                                  |
| Delete a category used by objects                       | Blocked with a message that includes the count.                    |
| Delete a manual event                                   | Its objects are deleted with it (cascade).                         |
| Delete an imported event                                | 403, imported events cannot be removed.                            |
| Reorder request with a missing, extra or duplicated id  | Validation error on `objects`.                                     |
| User with only `plan-calendar.edit`                     | Can use categories on events but cannot create or edit categories. |
