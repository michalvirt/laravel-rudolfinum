# Flow: Create a user

Administrators manage accounts on `/accounts`. There is no self-registration, no email verification and no password reset by email: an administrator creates the account and sets the password.

|           |                                                                                                                                                                                                                                          |
| --------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Actor     | User with `accounts.manage` (Administrators by default)                                                                                                                                                                                  |
| Page      | `/accounts`                                                                                                                                                                                                                              |
| Endpoints | `POST /accounts`, `PUT /accounts/{account}`, `PUT /accounts/{account}/password`, `DELETE /accounts/{account}`, `PUT /permission-groups/{permissionGroup}/notification-permissions`                                                       |
| Code      | `AccountController`, `AccountPasswordController`, `StoreAccountRequest`, `UpdateAccountRequest`, `ResetAccountPasswordRequest`, `PermissionGroupNotificationPermissionController`, `UpdatePermissionGroupNotificationPermissionsRequest` |
| Tables    | `users`, `permission_groups`, `sessions`                                                                                                                                                                                                 |

Besides the group, the dialog sets the notification permissions (`*.notification`, for example `importGoogleSheet.notification`, `planEventChange.notification`) of the account. The same permissions of a whole group are set in the "Notifications per permission group" table of the page (`PUT /permission-groups/{permissionGroup}/notification-permissions`, only the notification permissions of the group change). A user who has a notification permission both ways receives one email, see [email-notifications.md](email-notifications.md).

## 1. Create an account

```mermaid
sequenceDiagram
    autonumber
    actor A as Administrator
    participant UI as accounts page (React)
    participant MW as Middleware (auth, can:accounts.manage)
    participant R as StoreAccountRequest
    participant C as AccountController
    participant DB as Database

    A->>UI: Open /accounts
    UI->>MW: GET /accounts
    MW->>C: index()
    C->>DB: users (with group) + permission groups
    C-->>UI: props accounts[], permissionGroups[], notificationPermissions[]
    A->>UI: Fill in the create form
    UI->>MW: POST /accounts (name, surname, email, phone, permission_group_id, permissions[], password, password_confirmation)
    MW->>R: validate
    alt invalid
        R-->>UI: 302 back with errors (422 for JSON clients)
        UI-->>A: Field errors
    else valid
        R-->>C: validated data
        C->>DB: INSERT user (password hashed by the model cast, UUID id)
        C-->>UI: redirect back + flash "Account Jana Nováková was created."
        UI-->>A: Updated list and success snackbar
    end
```

If the user lacks the permission the middleware answers `403`; a guest is redirected to `/login`.

### Validation (`StoreAccountRequest`)

| Field                 | Rules                                                                                                             |
| --------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `name`                | required, string, max 255                                                                                         |
| `surname`             | required, string, max 255                                                                                         |
| `email`               | required, valid email, max 255, unique in `users`                                                                 |
| `phone`               | optional, string, max 255                                                                                         |
| `permission_group_id` | optional, must exist in `permission_groups`                                                                       |
| `permissions`         | optional list of notification permissions (values ending with `.notification`), no duplicates. Missing means none |
| `password`            | required, must match `password_confirmation`, `Password::defaults()`                                              |

`Password::defaults()` is Laravel's default (min 8) outside production. In production it requires at least 12 characters with mixed case, letters, numbers, symbols and not appearing in known data breaches.

A user created **without** a permission group can log in but has no permissions (every protected page returns 403).

## 2. What the new user can do

```mermaid
flowchart TD
    N([New account]) --> G{permission_group_id}
    G -- Administrators --> A[All nine permissions]
    G -- Planners --> P[calendar.view<br/>plan-calendar.view<br/>plan-calendar.edit<br/>planEventChange.notification]
    G -- Viewers --> V[calendar.view<br/>plan-calendar.view<br/>planEventChange.notification]
    G -- none --> X[Login works,<br/>no page beyond home]
    V --> AV[Can mark own available days]
    P --> AV
    A --> AV
    P --> AS[Can be assigned to events<br/>and can assign others]
    V --> AS2[Can be assigned to events]
    A --> AS
    X --> AS2
```

Every account, whatever its group, appears in the assignable staff list (`PlanCalendarBuilder::staff()`), see [assign-event-to-user.md](assign-event-to-user.md).

## 3. First sign-in

```mermaid
sequenceDiagram
    autonumber
    actor U as New user
    participant UI as login page
    participant C as AuthenticatedSessionController
    participant RL as RateLimiter
    participant S as Session

    U->>UI: Email and password (remember optional)
    UI->>C: POST /login
    C->>RL: tooManyAttempts(email|ip, 5)?
    alt locked out
        RL-->>UI: errors.email "Too many login attempts. Try again in N seconds."
    else
        C->>C: Auth::attempt()
        alt wrong credentials
            C->>RL: hit()
            C-->>UI: errors.email "These credentials do not match our records."
        else ok
            C->>RL: clear()
            C->>S: regenerate session id
            C-->>U: redirect to the intended URL, else /
        end
    end
```

Logged-in users are redirected away from `/login` (guest middleware). `POST /logout` invalidates the session and regenerates the CSRF token.

## 4. Manage the account afterwards

```mermaid
flowchart TD
    L[/accounts list/] --> E[Edit]
    L --> RP[Reset password]
    L --> D[Delete]
    E --> E1[PUT /accounts/id<br/>name, surname, email, phone, group,<br/>notification permissions<br/>password untouched]
    RP --> RP1[PUT /accounts/id/password<br/>password + confirmation]
    RP1 --> RP2[Set password, rotate remember_token]
    RP2 --> RP3{session driver = database?}
    RP3 -- yes --> RP4[Delete the account's sessions<br/>except the current one if it is the admin's own account]
    RP3 -- no --> RP5[Other sessions stay valid]
    D --> D1{Own account?}
    D1 -- yes --> D2[403 You cannot delete your own account.]
    D1 -- no --> D3[Delete user]
    D3 --> D4[plan_events positions set to NULL<br/>plan_availabilities deleted<br/>email log receiver_id set to NULL<br/>sessions rows of the user are left to expire]
```

| Action         | Notes                                                                                                                                                                                           |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Edit           | Email uniqueness ignores the account being edited. Sending an empty `permission_group_id` removes the user's group. A missing `permissions` removes the account's own notification permissions. |
| Reset password | Same password rules as create. Signs the user out everywhere (database sessions only).                                                                                                          |
| Delete         | An administrator cannot delete themselves, which prevents locking everyone out.                                                                                                                 |

## 5. Seeding users from the command line

`php artisan db:seed` creates the default groups, categories and demo users (see the README). For a production bootstrap, create the first administrator with a one-off command such as:

```bash
php artisan tinker --execute 'App\Models\User::create(["name" => "Ada", "surname" => "Admin", "email" => "ada@example.com", "password" => "a-strong-password", "permission_group_id" => App\Models\PermissionGroup::where("name", "Administrators")->value("id")]);'
```

The `PermissionGroupSeeder` must have run first.
