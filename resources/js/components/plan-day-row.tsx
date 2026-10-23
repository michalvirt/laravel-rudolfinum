import { Box, Typography } from '@mui/material';
import type { ReactNode } from 'react';
import type { PlanDay } from '@/types';

const formatDate = (date: string): string => {
    const [, month, day] = date.split('-');

    return `${day}.${month}.`;
};

export default function PlanDayRow({
    day,
    highlighted = false,
    children,
}: {
    day: PlanDay;
    highlighted?: boolean;
    children?: ReactNode;
}) {
    return (
        <Box
            component="li"
            aria-current={day.isToday ? 'date' : undefined}
            sx={{
                listStyle: 'none',
                display: 'grid',
                gridTemplateColumns: '64px 36px minmax(0, 1fr)',
                columnGap: 2,
                minHeight: 56,
                px: 2,
                py: 1,
                alignItems: 'start',
                borderBottom: 1,
                borderColor: 'divider',
                '&:last-of-type': { borderBottom: 0 },
                ...(day.isWeekend && { bgcolor: 'action.hover' }),
                ...(highlighted && {
                    bgcolor: 'warning.main',
                    color: 'warning.contrastText',
                }),
            }}
        >
            <Typography
                variant="body1"
                sx={{
                    alignSelf: 'start',
                    py: 0.5,
                    fontWeight: day.isToday ? 700 : 400,
                    ...(day.isToday && {
                        color: highlighted ? 'inherit' : 'primary.main',
                        textDecoration: 'underline',
                    }),
                }}
            >
                {formatDate(day.date)}
            </Typography>
            <Typography
                variant="body1"
                sx={{
                    py: 0.5,
                    color: highlighted
                        ? 'inherit'
                        : day.isWeekend
                          ? 'text.secondary'
                          : 'text.primary',
                }}
            >
                {day.weekday.slice(0, 2)}
            </Typography>
            <Box sx={{ minWidth: 0 }}>{children}</Box>
        </Box>
    );
}
