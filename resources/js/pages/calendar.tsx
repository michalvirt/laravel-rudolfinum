import { Head, Link } from '@inertiajs/react';
import ChevronLeftIcon from '@mui/icons-material/ChevronLeft';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import {
    Box,
    Button,
    IconButton,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import { calendar } from '@/routes';

type CalendarDay = {
    date: string;
    day: number;
    isCurrentMonth: boolean;
    isToday: boolean;
};

type PlanCalendarProps = {
    month: string;
    monthLabel: string;
    previousMonth: string;
    nextMonth: string;
    currentMonth: string;
    weekdays: string[];
    weeks: CalendarDay[][];
};

export default function Calendar({
    month,
    monthLabel,
    previousMonth,
    nextMonth,
    currentMonth,
    weekdays,
    weeks,
}: PlanCalendarProps) {
    return (
        <>
            <Head title="Plan calendar" />
            <Box sx={{ maxWidth: 960, mx: 'auto', p: 2 }}>
                <Stack
                    direction="row"
                    spacing={1}
                    sx={{
                        mb: 2,
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    <Stack
                        direction="row"
                        spacing={1}
                        sx={{ alignItems: 'center' }}
                    >
                        <IconButton
                            LinkComponent={Link}
                            href={
                                calendar({
                                    query: { month: previousMonth },
                                }).url
                            }
                            aria-label="Previous month"
                        >
                            <ChevronLeftIcon />
                        </IconButton>
                        <Typography
                            variant="h5"
                            component="h1"
                            sx={{ minWidth: 180, textAlign: 'center' }}
                        >
                            {monthLabel}
                        </Typography>
                        <IconButton
                            LinkComponent={Link}
                            href={calendar({ query: { month: nextMonth } }).url}
                            aria-label="Next month"
                        >
                            <ChevronRightIcon />
                        </IconButton>
                    </Stack>
                    <Button
                        LinkComponent={Link}
                        href={calendar().url}
                        variant="outlined"
                        disabled={month === currentMonth}
                    >
                        Today
                    </Button>
                </Stack>

                <Paper variant="outlined">
                    <Box
                        sx={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(7, 1fr)',
                        }}
                    >
                        {weekdays.map((weekday) => (
                            <Typography
                                key={weekday}
                                variant="subtitle2"
                                sx={{
                                    p: 1,
                                    textAlign: 'center',
                                    borderBottom: 1,
                                    borderColor: 'divider',
                                }}
                            >
                                {weekday}
                            </Typography>
                        ))}
                        {weeks.flat().map((day) => (
                            <Box
                                key={day.date}
                                sx={{
                                    minHeight: 96,
                                    p: 1,
                                    borderRight: 1,
                                    borderBottom: 1,
                                    borderColor: 'divider',
                                    opacity: day.isCurrentMonth ? 1 : 0.4,
                                    '&:nth-of-type(7n)': { borderRight: 0 },
                                }}
                            >
                                <Typography
                                    variant="body2"
                                    sx={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        width: 28,
                                        height: 28,
                                        borderRadius: '50%',
                                        ...(day.isToday && {
                                            bgcolor: 'primary.main',
                                            color: 'primary.contrastText',
                                        }),
                                    }}
                                >
                                    {day.day}
                                </Typography>
                            </Box>
                        ))}
                    </Box>
                </Paper>
            </Box>
        </>
    );
}
