import { Box, Typography } from '@mui/material';
import { Fragment } from 'react';
import type { ReactNode } from 'react';
import type { PlanAssignmentField } from '@/types';

export const staffColumnWidth = 560;

export const assignmentRows: {
    label: string;
    fields: { field: PlanAssignmentField; label: string }[];
}[] = [
    {
        label: 'Operators',
        fields: [
            { field: 'operator_1', label: 'Operator 1' },
            { field: 'operator_2', label: 'Operator 2' },
            { field: 'operator_3', label: 'Operator 3' },
        ],
    },
    {
        label: 'Engineers',
        fields: [
            { field: 'engineer_1', label: 'Engineer 1' },
            { field: 'engineer_2', label: 'Engineer 2' },
            { field: 'engineer_3', label: 'Engineer 3' },
        ],
    },
];

/**
 * The people of an event in two rows: the operators first, the engineers below them.
 * With `showRowLabels` every row starts with its name and `showEngineers` hides the engineers row.
 */
export function PlanStaffGrid({
    renderField,
    showRowLabels = false,
    showEngineers = true,
}: {
    renderField: (field: PlanAssignmentField, label: string) => ReactNode;
    showRowLabels?: boolean;
    showEngineers?: boolean;
}) {
    return (
        <Box
            sx={{
                display: 'grid',
                gridTemplateColumns: `${showRowLabels ? '80px ' : ''}repeat(3, minmax(0, 1fr))`,
                gap: 1,
            }}
        >
            {assignmentRows
                .filter((row) => showEngineers || row.label !== 'Engineers')
                .map((row) => (
                    <Fragment key={row.label}>
                        {showRowLabels && (
                            <Typography
                                variant="caption"
                                noWrap
                                sx={{
                                    color: 'inherit',
                                    opacity: 0.7,
                                    lineHeight: 'inherit',
                                }}
                            >
                                {row.label}
                            </Typography>
                        )}
                        {row.fields.map(({ field, label }) => (
                            <Box key={field} sx={{ minWidth: 0 }}>
                                {renderField(field, label)}
                            </Box>
                        ))}
                    </Fragment>
                ))}
        </Box>
    );
}

/**
 * An event on the left and its operators and engineers in the column on the right,
 * which is left out when there is no `staff`.
 */
export function PlanEventRow({
    staffWidth,
    staff,
    children,
}: {
    staffWidth: number;
    staff?: ReactNode;
    children: ReactNode;
}) {
    if (!staff) {
        return <Box sx={{ minWidth: 0 }}>{children}</Box>;
    }

    return (
        <Box
            sx={{
                display: 'grid',
                gridTemplateColumns: `minmax(0, 1fr) ${staffWidth}px`,
                columnGap: 2,
                alignItems: 'start',
            }}
        >
            <Box sx={{ minWidth: 0 }}>{children}</Box>
            <Box>{staff}</Box>
        </Box>
    );
}
