export type PlanCategory = {
    id: string;
    label: string;
    background_color: string;
    font_color: string;
};

export type PlanObject = {
    id: string;
    text: string;
    plan_event_category_id: string;
    category: PlanCategory;
};

export type PlanStaffMember = {
    id: string;
    name: string;
    surname: string;
};

export type PlanAssignmentField =
    | 'operator_1'
    | 'operator_2'
    | 'operator_3'
    | 'engineer_1'
    | 'engineer_2'
    | 'engineer_3';

export type PlanAssignments = Record<PlanAssignmentField, string | null>;

export type PlanEvent = {
    id: string;
    name: string | null;
    showOriginalText: boolean;
    originalTextChanged: boolean;
    awaitsChangeAcknowledgement: boolean;
    isImported: boolean;
    hasChanges: boolean;
    changedFields: string[];
    changedName: string | null;
    assignments: PlanAssignments;
    objects: PlanObject[];
};

export type PlanDay = {
    date: string;
    day: number;
    weekday: string;
    isWeekend: boolean;
    isToday: boolean;
    availableUserIds: string[];
    events: PlanEvent[];
};

export type PlanMonth = {
    month: string;
    monthLabel: string;
    previousMonth: string;
    nextMonth: string;
    currentMonth: string;
    staffVisible: boolean;
    days: PlanDay[];
};
