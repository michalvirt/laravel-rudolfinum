import { Link } from '@inertiajs/react';
import ChevronLeftIcon from '@mui/icons-material/ChevronLeft';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import { Button, IconButton, Stack, Typography } from '@mui/material';
import type { ReactNode } from 'react';
import type { PlanMonth } from '@/types';

export default function PlanMonthNavigation({
    calendar,
    hrefFor,
    actions,
}: {
    calendar: Pick<
        PlanMonth,
        'month' | 'monthLabel' | 'previousMonth' | 'nextMonth' | 'currentMonth'
    >;
    hrefFor: (month?: string) => string;
    actions?: ReactNode;
}) {
    return (
        <Stack
            direction="row"
            spacing={1}
            sx={{
                mb: 2,
                alignItems: 'center',
                justifyContent: 'space-between',
            }}
        >
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <IconButton
                    LinkComponent={Link}
                    href={hrefFor(calendar.previousMonth)}
                    aria-label="Previous month"
                >
                    <ChevronLeftIcon />
                </IconButton>
                <Typography
                    variant="h5"
                    component="h1"
                    sx={{ minWidth: 180, textAlign: 'center' }}
                >
                    {calendar.monthLabel}
                </Typography>
                <IconButton
                    LinkComponent={Link}
                    href={hrefFor(calendar.nextMonth)}
                    aria-label="Next month"
                >
                    <ChevronRightIcon />
                </IconButton>
            </Stack>
            <Stack direction="row" spacing={1}>
                {actions}
                <Button
                    LinkComponent={Link}
                    href={hrefFor()}
                    variant="outlined"
                    disabled={calendar.month === calendar.currentMonth}
                >
                    Today
                </Button>
            </Stack>
        </Stack>
    );
}
