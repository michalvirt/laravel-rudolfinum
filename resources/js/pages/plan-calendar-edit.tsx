import { Form, Head, router, usePage } from '@inertiajs/react';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import WarningAmberIcon from '@mui/icons-material/WarningAmber';
import {
    Alert,
    Box,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
    Paper,
    Stack,
    Switch,
    TextField,
    Tooltip,
    Typography,
} from '@mui/material';
import { useEffect, useState } from 'react';
import type { DragEvent, KeyboardEvent } from 'react';
import {
    destroy as destroyEvent,
    store as storeEvent,
    update as updateEvent,
} from '@/actions/App/Http/Controllers/PlanEventController';
import updateStaffVisibility from '@/actions/App/Http/Controllers/PlanCalendarStaffVisibilityController';
import assignStaff from '@/actions/App/Http/Controllers/PlanEventAssignmentController';
import reorderObjects from '@/actions/App/Http/Controllers/PlanEventObjectOrderController';
import {
    destroy as destroyObject,
    store as storeObject,
    update as updateObject,
} from '@/actions/App/Http/Controllers/PlanEventObjectController';
import FlashSnackbar from '@/components/flash-snackbar';
import PlanDayRow from '@/components/plan-day-row';
import PlanMonthNavigation from '@/components/plan-month-navigation';
import PlanObjectChip from '@/components/plan-object-chip';
import {
    assignmentRows,
    PlanEventRow,
    PlanStaffGrid,
    staffColumnWidth,
} from '@/components/plan-staff-grid';
import { planCalendarEdit } from '@/routes';
import type {
    PlanAssignmentField,
    PlanCategory,
    PlanDay,
    PlanEvent,
    PlanMonth,
    PlanObject,
    PlanStaffMember,
} from '@/types';

type PlanCalendarEditProps = PlanMonth & {
    categories: PlanCategory[];
    staff: PlanStaffMember[];
};

type ObjectDialogState =
    | { type: 'create'; event: PlanEvent }
    | { type: 'edit'; object: PlanObject };

function ObjectDialog({
    state,
    categories,
    onClose,
}: {
    state: ObjectDialogState;
    categories: PlanCategory[];
    onClose: () => void;
}) {
    const object = state.type === 'edit' ? state.object : null;
    const formProps =
        object === null ? storeObject.form() : updateObject.form(object.id);

    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="xs">
            <Form {...formProps} onSuccess={onClose}>
                {({ errors, processing }) => (
                    <>
                        <DialogTitle>
                            {object === null ? 'Add object' : 'Edit object'}
                        </DialogTitle>
                        <DialogContent>
                            <Stack spacing={2} sx={{ pt: 1 }}>
                                {state.type === 'create' && (
                                    <input
                                        type="hidden"
                                        name="plan_event_id"
                                        value={state.event.id}
                                    />
                                )}
                                <TextField
                                    name="text"
                                    label="Text"
                                    defaultValue={object?.text}
                                    autoComplete="off"
                                    autoFocus
                                    required
                                    error={Boolean(errors.text)}
                                    helperText={errors.text}
                                />
                                <TextField
                                    select
                                    name="plan_event_category_id"
                                    label="Category"
                                    defaultValue={
                                        object?.plan_event_category_id ??
                                        categories[0]?.id ??
                                        ''
                                    }
                                    required
                                    error={Boolean(
                                        errors.plan_event_category_id,
                                    )}
                                    helperText={errors.plan_event_category_id}
                                    slotProps={{
                                        select: { native: true },
                                        inputLabel: { shrink: true },
                                    }}
                                >
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.label}
                                        </option>
                                    ))}
                                </TextField>
                            </Stack>
                        </DialogContent>
                        <DialogActions>
                            <Button onClick={onClose}>Cancel</Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={processing}
                            >
                                {object === null ? 'Add' : 'Save'}
                            </Button>
                        </DialogActions>
                    </>
                )}
            </Form>
        </Dialog>
    );
}

function moveItem<T>(items: T[], from: number, to: number): T[] {
    const next = [...items];
    const [moved] = next.splice(from, 1);

    next.splice(to, 0, moved);

    return next;
}

function SortableObjects({
    event,
    onEditObject,
}: {
    event: PlanEvent;
    onEditObject: (object: PlanObject) => void;
}) {
    const [objects, setObjects] = useState(event.objects);
    const [draggedId, setDraggedId] = useState<string | null>(null);
    const [overId, setOverId] = useState<string | null>(null);

    useEffect(() => setObjects(event.objects), [event.objects]);

    const saveOrder = (next: PlanObject[]) => {
        setObjects(next);
        router.put(
            reorderObjects(event.id).url,
            { objects: next.map((object) => object.id) },
            { preserveScroll: true },
        );
    };

    const resetDrag = () => {
        setDraggedId(null);
        setOverId(null);
    };

    const handleDrop = (dropEvent: DragEvent, targetId: string) => {
        dropEvent.preventDefault();

        const from = objects.findIndex((object) => object.id === draggedId);
        const to = objects.findIndex((object) => object.id === targetId);

        resetDrag();

        if (from !== -1 && to !== -1 && from !== to) {
            saveOrder(moveItem(objects, from, to));
        }
    };

    const handleKeyDown = (keyEvent: KeyboardEvent, index: number) => {
        const step =
            keyEvent.key === 'ArrowLeft'
                ? -1
                : keyEvent.key === 'ArrowRight'
                  ? 1
                  : 0;
        const target = index + step;

        if (
            !keyEvent.altKey ||
            step === 0 ||
            target < 0 ||
            target >= objects.length
        ) {
            return;
        }

        keyEvent.preventDefault();
        saveOrder(moveItem(objects, index, target));
    };

    return (
        <>
            {objects.map((object, index) => (
                <Box
                    key={object.id}
                    draggable
                    title="Drag to change the order (or press Alt + arrow keys)"
                    onDragStart={(dragEvent: DragEvent) => {
                        dragEvent.dataTransfer.effectAllowed = 'move';
                        dragEvent.dataTransfer.setData('text/plain', object.id);
                        setDraggedId(object.id);
                    }}
                    onDragOver={(dragEvent: DragEvent) => {
                        if (draggedId !== null) {
                            dragEvent.preventDefault();
                            dragEvent.dataTransfer.dropEffect = 'move';
                            setOverId(object.id);
                        }
                    }}
                    onDrop={(dragEvent: DragEvent) =>
                        handleDrop(dragEvent, object.id)
                    }
                    onDragEnd={resetDrag}
                    onKeyDown={(keyEvent: KeyboardEvent) =>
                        handleKeyDown(keyEvent, index)
                    }
                    sx={{
                        cursor: 'grab',
                        borderRadius: 4,
                        opacity: draggedId === object.id ? 0.4 : 1,
                        outline:
                            overId === object.id && draggedId !== object.id
                                ? '2px dashed'
                                : 'none',
                        outlineColor: 'primary.main',
                        outlineOffset: 2,
                    }}
                >
                    <PlanObjectChip
                        object={object}
                        onClick={() => onEditObject(object)}
                        onDelete={() =>
                            router.delete(destroyObject(object.id).url, {
                                preserveScroll: true,
                            })
                        }
                    />
                </Box>
            ))}
        </>
    );
}

function StaffAssignments({
    event,
    staff,
    availableIds,
    busyIds,
}: {
    event: PlanEvent;
    staff: PlanStaffMember[];
    availableIds: string[];
    busyIds: string[];
}) {
    const [error, setError] = useState<string | null>(null);
    const takenIds = Object.values(event.assignments);

    const assign = (field: PlanAssignmentField, userId: string) => {
        const assignments = {
            ...event.assignments,
            [field]: userId === '' ? null : userId,
        };

        // Positions are filled one after another, so clearing one moves the following ones up.
        for (const row of assignmentRows) {
            const selected = row.fields
                .map(({ field: rowField }) => assignments[rowField])
                .filter((id) => id !== null);

            row.fields.forEach(({ field: rowField }, index) => {
                assignments[rowField] = selected[index] ?? null;
            });
        }

        router.put(assignStaff(event.id).url, assignments, {
            preserveScroll: true,
            onStart: () => setError(null),
            onError: (errors) => setError(Object.values(errors)[0]),
        });
    };

    const availableStaff = availableIds
        .map((id) => staff.find((member) => member.id === id))
        .filter((member) => member !== undefined);
    const otherStaff = staff.filter(
        (member) => !availableIds.includes(member.id),
    );

    const renderOption =
        (field: PlanAssignmentField) => (member: PlanStaffMember) => (
            <option
                key={member.id}
                value={member.id}
                disabled={
                    member.id !== event.assignments[field] &&
                    (takenIds.includes(member.id) ||
                        busyIds.includes(member.id))
                }
            >
                {member.name} {member.surname}
            </option>
        );

    const isLocked = (field: PlanAssignmentField): boolean => {
        const fields = assignmentRows
            .map((row) => row.fields.map((item) => item.field))
            .find((rowFields) => rowFields.includes(field));
        const previous = fields?.[fields.indexOf(field) - 1];

        return previous !== undefined && event.assignments[previous] === null;
    };

    return (
        <Stack spacing={0.5}>
            <PlanStaffGrid
                renderField={(field, label) => (
                    <TextField
                        select
                        fullWidth
                        size="small"
                        label={label}
                        disabled={isLocked(field)}
                        value={event.assignments[field] ?? ''}
                        onChange={(changeEvent) =>
                            assign(field, changeEvent.target.value)
                        }
                        slotProps={{
                            select: { native: true },
                            inputLabel: { shrink: true },
                        }}
                    >
                        <option value="">-</option>
                        {availableStaff.length > 0 ? (
                            <>
                                <optgroup label="Have time">
                                    {availableStaff.map(renderOption(field))}
                                </optgroup>
                                <optgroup label="Others">
                                    {otherStaff.map(renderOption(field))}
                                </optgroup>
                            </>
                        ) : (
                            otherStaff.map(renderOption(field))
                        )}
                    </TextField>
                )}
            />
            {error !== null && (
                <Typography variant="caption" color="error">
                    {error}
                </Typography>
            )}
        </Stack>
    );
}

function AssignedDaysSummary({
    staff,
    days,
}: {
    staff: PlanStaffMember[];
    days: PlanDay[];
}) {
    const assignedDays = (userId: string): number =>
        days.filter((day) =>
            day.events.some((event) =>
                Object.values(event.assignments).includes(userId),
            ),
        ).length;

    return (
        <Paper variant="outlined" sx={{ mb: 2, p: 1.5 }}>
            <Stack spacing={1}>
                <Typography variant="subtitle2">Assigned days</Typography>
                {staff.length === 0 ? (
                    <Typography variant="body2" color="text.secondary">
                        There is no staff to assign.
                    </Typography>
                ) : (
                    <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 0.75 }}>
                        {staff.map((member) => {
                            const count = assignedDays(member.id);

                            return (
                                <Chip
                                    key={member.id}
                                    size="small"
                                    variant={
                                        count === 0 ? 'outlined' : 'filled'
                                    }
                                    label={`${member.name} ${member.surname}: ${count}`}
                                />
                            );
                        })}
                    </Stack>
                )}
            </Stack>
        </Paper>
    );
}

function EventEditor({
    event,
    staff,
    availableIds,
    busyIds,
    onAddObject,
    onEditObject,
}: {
    event: PlanEvent;
    staff: PlanStaffMember[];
    availableIds: string[];
    busyIds: string[];
    onAddObject: () => void;
    onEditObject: (object: PlanObject) => void;
}) {
    return (
        <PlanEventRow
            staffWidth={staffColumnWidth}
            staff={
                <StaffAssignments
                    event={event}
                    staff={staff}
                    availableIds={availableIds}
                    busyIds={busyIds}
                />
            }
        >
            <Stack spacing={0.5}>
                <Stack
                    direction="row"
                    spacing={1}
                    sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 0.5 }}
                >
                    {event.name !== null && (
                        <>
                            <Typography
                                variant="body2"
                                sx={{
                                    color: event.showOriginalText
                                        ? 'text.primary'
                                        : 'text.disabled',
                                    textDecoration: event.showOriginalText
                                        ? 'none'
                                        : 'line-through',
                                }}
                            >
                                {event.name}
                            </Typography>
                            <FormControlLabel
                                control={
                                    <Switch
                                        size="small"
                                        checked={event.showOriginalText}
                                        onChange={(changeEvent) =>
                                            router.put(
                                                updateEvent(event.id).url,
                                                {
                                                    show_original_text:
                                                        changeEvent.target
                                                            .checked,
                                                },
                                                { preserveScroll: true },
                                            )
                                        }
                                    />
                                }
                                label="Show original text"
                                slotProps={{
                                    typography: { variant: 'caption' },
                                }}
                            />
                        </>
                    )}
                    {event.name === null && (
                        <Typography
                            variant="body2"
                            sx={{ color: 'text.secondary' }}
                        >
                            Event added by hand
                        </Typography>
                    )}
                    {event.hasChanges && (
                        <Tooltip
                            title={`Changed in the sheet: ${event.changedName ?? event.changedFields.join(', ')}`}
                        >
                            <Chip
                                icon={<WarningAmberIcon />}
                                label="Changed in sheet"
                                color="warning"
                                size="small"
                            />
                        </Tooltip>
                    )}
                    {!event.isImported && (
                        <Tooltip title="Remove the event">
                            <IconButton
                                size="small"
                                aria-label="Remove event"
                                onClick={() =>
                                    router.delete(destroyEvent(event.id).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <DeleteIcon fontSize="small" />
                            </IconButton>
                        </Tooltip>
                    )}
                </Stack>
                <Stack
                    direction="row"
                    spacing={0.5}
                    sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 0.5 }}
                >
                    <SortableObjects
                        event={event}
                        onEditObject={onEditObject}
                    />
                    <Button
                        size="small"
                        startIcon={<AddIcon />}
                        onClick={onAddObject}
                    >
                        Add object
                    </Button>
                </Stack>
            </Stack>
        </PlanEventRow>
    );
}

export default function PlanCalendarEdit({
    categories,
    staff,
    ...calendar
}: PlanCalendarEditProps) {
    const { user } = usePage().props.auth;
    const [dialog, setDialog] = useState<ObjectDialogState | null>(null);
    const canManageCategories =
        user?.permissions.includes('plan-categories.manage') ?? false;

    return (
        <>
            <Head title="Plan calendar edit" />
            <Box sx={{ maxWidth: 1600, mx: 'auto', p: 2 }}>
                <PlanMonthNavigation
                    calendar={calendar}
                    hrefFor={(month) =>
                        planCalendarEdit(
                            month ? { query: { month } } : undefined,
                        ).url
                    }
                    actions={
                        <>
                            <FormControlLabel
                                control={
                                    <Switch
                                        checked={calendar.staffVisible}
                                        onChange={(changeEvent) =>
                                            router
                                                .optimistic(() => ({
                                                    staffVisible:
                                                        changeEvent.target
                                                            .checked,
                                                }))
                                                .put(
                                                    updateStaffVisibility().url,
                                                    {
                                                        month: calendar.month,
                                                        visible:
                                                            changeEvent.target
                                                                .checked,
                                                    },
                                                    { preserveScroll: true },
                                                )
                                        }
                                    />
                                }
                                label="Show operators and engineers in view"
                                sx={{ m: 0 }}
                            />
                        </>
                    }
                />

                <AssignedDaysSummary staff={staff} days={calendar.days} />

                {categories.length === 0 && (
                    <Alert severity="info" sx={{ mb: 2 }}>
                        There are no categories yet, so objects cannot be added.
                        {canManageCategories
                            ? ' Create one on the categories page.'
                            : ' Ask an administrator to create one.'}
                    </Alert>
                )}

                <Paper variant="outlined" component="ol" sx={{ m: 0, p: 0 }}>
                    {calendar.days.map((day) => (
                        <PlanDayRow key={day.date} day={day}>
                            <Stack spacing={1.5}>
                                {day.events.map((event) => (
                                    <EventEditor
                                        key={event.id}
                                        event={event}
                                        staff={staff}
                                        availableIds={day.availableUserIds}
                                        busyIds={day.events
                                            .filter(
                                                (other) =>
                                                    other.id !== event.id,
                                            )
                                            .flatMap((other) =>
                                                Object.values(
                                                    other.assignments,
                                                ),
                                            )
                                            .filter((id) => id !== null)}
                                        onAddObject={() =>
                                            setDialog({ type: 'create', event })
                                        }
                                        onEditObject={(object) =>
                                            setDialog({ type: 'edit', object })
                                        }
                                    />
                                ))}
                                <Box>
                                    <Button
                                        size="small"
                                        variant="outlined"
                                        startIcon={<AddIcon />}
                                        onClick={() =>
                                            router.post(
                                                storeEvent().url,
                                                { date: day.date },
                                                { preserveScroll: true },
                                            )
                                        }
                                        sx={{ whiteSpace: 'nowrap' }}
                                    >
                                        Add event
                                    </Button>
                                </Box>
                            </Stack>
                        </PlanDayRow>
                    ))}
                </Paper>
            </Box>

            {dialog !== null && (
                <ObjectDialog
                    state={dialog}
                    categories={categories}
                    onClose={() => setDialog(null)}
                />
            )}

            <FlashSnackbar />
        </>
    );
}
