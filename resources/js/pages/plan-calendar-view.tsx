import { Head, router, usePage } from '@inertiajs/react';
import {
    Box,
    Button,
    Checkbox,
    Divider,
    FormControlLabel,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import updateAvailability from '@/actions/App/Http/Controllers/PlanCalendarAvailabilityController';
import acknowledgeChange from '@/actions/App/Http/Controllers/PlanEventChangeAcknowledgementController';
import PlanDayRow from '@/components/plan-day-row';
import PlanMonthNavigation from '@/components/plan-month-navigation';
import PlanObjectChip from '@/components/plan-object-chip';
import {
    PlanEventRow,
    PlanStaffGrid,
    staffColumnWidth,
} from '@/components/plan-staff-grid';
import { planCalendarView } from '@/routes';
import type { PlanDay, PlanEvent, PlanMonth, PlanStaffMember } from '@/types';

type PlanCalendarViewProps = PlanMonth & { staff: PlanStaffMember[] };

const availabilityWidth = 150;

const setAvailability = (
    day: PlanDay,
    userId: string,
    available: boolean,
): void => {
    router
        .optimistic<{ days: PlanDay[] }>((props) => ({
            days: props.days.map((item) =>
                item.date === day.date
                    ? {
                          ...item,
                          availableUserIds: available
                              ? [
                                    ...item.availableUserIds.filter(
                                        (id) => id !== userId,
                                    ),
                                    userId,
                                ]
                              : item.availableUserIds.filter(
                                    (id) => id !== userId,
                                ),
                      }
                    : item,
            ),
        }))
        .put(
            updateAvailability().url,
            { date: day.date, available },
            { preserveScroll: true },
        );
};

const acknowledgeEventChange = (event: PlanEvent): void => {
    router.post(acknowledgeChange(event.id).url, {}, { preserveScroll: true });
};

const hasEngineers = (event: PlanEvent): boolean =>
    Object.entries(event.assignments).some(
        ([field, userId]) => field.startsWith('engineer_') && userId !== null,
    );

const showsOriginalText = (event: PlanEvent): boolean =>
    event.showOriginalText && event.name !== null && event.name !== '';

export default function PlanCalendarView({
    staff,
    ...calendar
}: PlanCalendarViewProps) {
    const { user } = usePage().props.auth;
    const isAvailabilityEditable = calendar.month > calendar.currentMonth;
    const staffNames = new Map(
        staff.map((member) => [member.id, `${member.name} ${member.surname}`]),
    );

    return (
        <>
            <Head title="Plan calendar view" />
            <Box sx={{ maxWidth: 1600, mx: 'auto', p: 2 }}>
                <PlanMonthNavigation
                    calendar={calendar}
                    hrefFor={(month) =>
                        planCalendarView(
                            month ? { query: { month } } : undefined,
                        ).url
                    }
                />

                <Paper variant="outlined" component="ol" sx={{ m: 0, p: 0 }}>
                    {calendar.days.map((day) => (
                        <PlanDayRow
                            key={day.date}
                            day={day}
                            highlighted={day.events.some(
                                (event) => event.awaitsChangeAcknowledgement,
                            )}
                        >
                            <Box sx={{ display: 'flex', columnGap: 2 }}>
                                {user && (
                                    <FormControlLabel
                                        sx={{
                                            flex: `0 0 ${availabilityWidth}px`,
                                            m: 0,
                                            alignSelf: 'flex-start',
                                            color: 'inherit',
                                        }}
                                        control={
                                            <Checkbox
                                                size="small"
                                                color="success"
                                                disabled={
                                                    !isAvailabilityEditable
                                                }
                                                checked={day.availableUserIds.includes(
                                                    user.id,
                                                )}
                                                onChange={(changeEvent) =>
                                                    setAvailability(
                                                        day,
                                                        user.id,
                                                        changeEvent.target
                                                            .checked,
                                                    )
                                                }
                                            />
                                        }
                                        label="Ready to work"
                                        slotProps={{
                                            typography: { variant: 'body2' },
                                        }}
                                    />
                                )}
                                <Stack
                                    spacing={1}
                                    divider={<Divider flexItem />}
                                    sx={{ flex: 1, minWidth: 0 }}
                                >
                                    {day.events.map((event) => (
                                        <PlanEventRow
                                            key={event.id}
                                            staffWidth={staffColumnWidth}
                                            staff={
                                                calendar.staffVisible ? (
                                                    <PlanStaffGrid
                                                        showRowLabels
                                                        showEngineers={hasEngineers(
                                                            event,
                                                        )}
                                                        renderField={(
                                                            field,
                                                            label,
                                                        ) => {
                                                            const userId =
                                                                event
                                                                    .assignments[
                                                                    field
                                                                ];
                                                            const name =
                                                                userId === null
                                                                    ? undefined
                                                                    : staffNames.get(
                                                                          userId,
                                                                      );

                                                            return (
                                                                <Typography
                                                                    variant="body2"
                                                                    noWrap
                                                                    title={
                                                                        name
                                                                            ? `${label}: ${name}`
                                                                            : undefined
                                                                    }
                                                                >
                                                                    {name ??
                                                                        '\u00a0'}
                                                                </Typography>
                                                            );
                                                        }}
                                                    />
                                                ) : undefined
                                            }
                                        >
                                            <Stack spacing={0.5}>
                                                {showsOriginalText(event) && (
                                                    <Typography variant="body2">
                                                        {event.name}
                                                    </Typography>
                                                )}
                                                {event.awaitsChangeAcknowledgement && (
                                                    <Box>
                                                        <Button
                                                            size="small"
                                                            variant="outlined"
                                                            color="inherit"
                                                            onClick={() =>
                                                                acknowledgeEventChange(
                                                                    event,
                                                                )
                                                            }
                                                        >
                                                            I know about the
                                                            change
                                                        </Button>
                                                    </Box>
                                                )}
                                                {event.objects.length > 0 && (
                                                    <Stack
                                                        direction="row"
                                                        spacing={0.5}
                                                        sx={{
                                                            alignItems:
                                                                'center',
                                                            flexWrap: 'wrap',
                                                            rowGap: 0.5,
                                                        }}
                                                    >
                                                        {event.objects.map(
                                                            (object) => (
                                                                <PlanObjectChip
                                                                    key={
                                                                        object.id
                                                                    }
                                                                    object={
                                                                        object
                                                                    }
                                                                />
                                                            ),
                                                        )}
                                                    </Stack>
                                                )}
                                            </Stack>
                                        </PlanEventRow>
                                    ))}
                                </Stack>
                            </Box>
                        </PlanDayRow>
                    ))}
                </Paper>
            </Box>
        </>
    );
}
