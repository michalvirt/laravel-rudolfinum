import { Form, Head } from '@inertiajs/react';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import {
    Box,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
    Tooltip,
    Typography,
} from '@mui/material';
import { useState } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/PlanEventCategoryController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import FlashSnackbar from '@/components/flash-snackbar';

type Category = {
    id: string;
    label: string;
    background_color: string;
    font_color: string;
    objectsCount: number;
};

type PlanCategoriesProps = {
    categories: Category[];
};

type DialogState =
    | { type: 'create' }
    | { type: 'edit'; category: Category }
    | { type: 'delete'; category: Category };

type DialogProps = {
    onClose: () => void;
};

function CategoryDialog({
    category,
    onClose,
}: DialogProps & { category: Category | null }) {
    const [label, setLabel] = useState(category?.label ?? '');
    const [backgroundColor, setBackgroundColor] = useState(
        category?.background_color ?? '#1565c0',
    );
    const [fontColor, setFontColor] = useState(
        category?.font_color ?? '#ffffff',
    );
    const formProps =
        category === null ? store.form() : update.form(category.id);

    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="xs">
            <Form {...formProps} onSuccess={onClose}>
                {({ errors, processing }) => (
                    <>
                        <DialogTitle>
                            {category === null
                                ? 'Add category'
                                : 'Edit category'}
                        </DialogTitle>
                        <DialogContent>
                            <Stack spacing={2} sx={{ pt: 1 }}>
                                <TextField
                                    name="label"
                                    label="Label"
                                    value={label}
                                    onChange={(event) =>
                                        setLabel(event.target.value)
                                    }
                                    autoComplete="off"
                                    autoFocus
                                    required
                                    error={Boolean(errors.label)}
                                    helperText={errors.label}
                                />
                                <Stack direction="row" spacing={2}>
                                    <TextField
                                        name="background_color"
                                        type="color"
                                        label="Background color"
                                        value={backgroundColor}
                                        onChange={(event) =>
                                            setBackgroundColor(
                                                event.target.value,
                                            )
                                        }
                                        fullWidth
                                        required
                                        error={Boolean(errors.background_color)}
                                        helperText={errors.background_color}
                                        slotProps={{
                                            inputLabel: { shrink: true },
                                        }}
                                    />
                                    <TextField
                                        name="font_color"
                                        type="color"
                                        label="Font color"
                                        value={fontColor}
                                        onChange={(event) =>
                                            setFontColor(event.target.value)
                                        }
                                        fullWidth
                                        required
                                        error={Boolean(errors.font_color)}
                                        helperText={errors.font_color}
                                        slotProps={{
                                            inputLabel: { shrink: true },
                                        }}
                                    />
                                </Stack>
                                <Stack
                                    direction="row"
                                    spacing={1}
                                    sx={{ alignItems: 'center' }}
                                >
                                    <Typography
                                        variant="body2"
                                        sx={{ color: 'text.secondary' }}
                                    >
                                        Preview
                                    </Typography>
                                    <Chip
                                        label={label || 'Label'}
                                        size="small"
                                        sx={{
                                            bgcolor: backgroundColor,
                                            color: fontColor,
                                        }}
                                    />
                                </Stack>
                            </Stack>
                        </DialogContent>
                        <DialogActions>
                            <Button onClick={onClose}>Cancel</Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={processing}
                            >
                                {category === null ? 'Create' : 'Save'}
                            </Button>
                        </DialogActions>
                    </>
                )}
            </Form>
        </Dialog>
    );
}

export default function PlanCategories({ categories }: PlanCategoriesProps) {
    const [dialog, setDialog] = useState<DialogState | null>(null);

    const closeDialog = () => setDialog(null);

    return (
        <>
            <Head title="Plan categories" />
            <Box sx={{ maxWidth: 1600, mx: 'auto', p: 2 }}>
                <Stack
                    direction="row"
                    spacing={1}
                    sx={{
                        mb: 2,
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    <Typography variant="h5" component="h1">
                        Plan categories
                    </Typography>
                    <Stack direction="row" spacing={1}>
                        <Button
                            variant="contained"
                            startIcon={<AddIcon />}
                            onClick={() => setDialog({ type: 'create' })}
                        >
                            Add category
                        </Button>
                    </Stack>
                </Stack>

                <TableContainer component={Paper} variant="outlined">
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableCell>Preview</TableCell>
                                <TableCell>Label</TableCell>
                                <TableCell>Background</TableCell>
                                <TableCell>Font</TableCell>
                                <TableCell>Objects</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {categories.map((category) => (
                                <TableRow key={category.id} hover>
                                    <TableCell>
                                        <Chip
                                            label={category.label}
                                            size="small"
                                            sx={{
                                                bgcolor:
                                                    category.background_color,
                                                color: category.font_color,
                                            }}
                                        />
                                    </TableCell>
                                    <TableCell>{category.label}</TableCell>
                                    <TableCell>
                                        {category.background_color}
                                    </TableCell>
                                    <TableCell>{category.font_color}</TableCell>
                                    <TableCell>
                                        {category.objectsCount}
                                    </TableCell>
                                    <TableCell
                                        align="right"
                                        sx={{ whiteSpace: 'nowrap' }}
                                    >
                                        <Tooltip title="Edit">
                                            <IconButton
                                                aria-label={`Edit ${category.label}`}
                                                onClick={() =>
                                                    setDialog({
                                                        type: 'edit',
                                                        category,
                                                    })
                                                }
                                            >
                                                <EditIcon />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip title="Delete">
                                            <IconButton
                                                aria-label={`Delete ${category.label}`}
                                                color="error"
                                                onClick={() =>
                                                    setDialog({
                                                        type: 'delete',
                                                        category,
                                                    })
                                                }
                                            >
                                                <DeleteIcon />
                                            </IconButton>
                                        </Tooltip>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {categories.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} align="center">
                                        No categories yet.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Box>

            {dialog?.type === 'create' && (
                <CategoryDialog category={null} onClose={closeDialog} />
            )}
            {dialog?.type === 'edit' && (
                <CategoryDialog
                    category={dialog.category}
                    onClose={closeDialog}
                />
            )}
            {dialog?.type === 'delete' && (
                <ConfirmDeleteDialog
                    title="Delete category"
                    form={destroy.form(dialog.category.id)}
                    errorField="category"
                    onClose={closeDialog}
                >
                    Delete the category {dialog.category.label}? This cannot be
                    undone.
                </ConfirmDeleteDialog>
            )}

            <FlashSnackbar />
        </>
    );
}
