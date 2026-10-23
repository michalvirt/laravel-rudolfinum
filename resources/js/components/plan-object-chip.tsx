import { Chip, Tooltip } from '@mui/material';
import type { PlanObject } from '@/types';

export default function PlanObjectChip({
    object,
    onClick,
    onDelete,
}: {
    object: PlanObject;
    onClick?: () => void;
    onDelete?: () => void;
}) {
    const { background_color, font_color, label } = object.category;

    return (
        <Tooltip title={label}>
            <Chip
                label={object.text}
                size="small"
                onClick={onClick}
                onDelete={onDelete}
                sx={{
                    bgcolor: background_color,
                    color: font_color,
                    '&:hover': { bgcolor: background_color, opacity: 0.9 },
                    '& .MuiChip-deleteIcon': {
                        color: font_color,
                        opacity: 0.7,
                    },
                }}
            />
        </Tooltip>
    );
}
