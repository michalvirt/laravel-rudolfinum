export type Permission =
    | 'calendar.view'
    | 'plan-calendar.view'
    | 'accounts.manage'
    | 'plan-import.manage'
    | 'plan-calendar.edit'
    | 'plan-categories.manage'
    | 'email-notifications.manage'
    | 'importGoogleSheet.notification'
    | 'planEventChange.notification';

export type User = {
    id: string;
    name: string;
    surname: string;
    email: string;
    phone: string | null;
    permission_group: string | null;
    permissions: Permission[];
};

export type Auth = {
    user: User | null;
};
