import { Form, Head, router, useHttp, usePage } from '@inertiajs/react';
import AddIcon from '@mui/icons-material/Add';
import CheckIcon from '@mui/icons-material/Check';
import DeleteIcon from '@mui/icons-material/Delete';
import DoneAllIcon from '@mui/icons-material/DoneAll';
import EditIcon from '@mui/icons-material/Edit';
import PlayArrowIcon from '@mui/icons-material/PlayArrow';
import VisibilityIcon from '@mui/icons-material/Visibility';
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
    accept,
    acceptAll,
} from '@/actions/App/Http/Controllers/PlanImportChangeController';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/PlanImportController';
import PlanImportRunController from '@/actions/App/Http/Controllers/PlanImportRunController';
import PlanImportTabsController from '@/actions/App/Http/Controllers/PlanImportTabsController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import FlashSnackbar from '@/components/flash-snackbar';

type ImportField =
    | 'weekday'
    | 'service'
    | 'name'
    | 'comment'
    | 'operator_1'
    | 'operator_2'
    | 'htl_1'
    | 'htl_2';

type SheetRow = {
    id: string;
    row_number: number;
    date: string;
    changed_fields: Partial<Record<ImportField, string | null>>;
    hasChanges: boolean;
} & Record<ImportField, string | null>;

type SheetImport = {
    id: string;
    hasApiKey: boolean;
    spreadsheet_id: string;
    sheet_names: string[];
    sheet_name: string;
    first_row: number;
    last_row: number;
    year: number;
    auto_import: boolean;
    cron_expression: string;
    last_imported_at: string | null;
    last_import_status: 'success' | 'failed' | null;
    last_import_message: string | null;
    changedCount: number;
};

type Defaults = {
    hasApiKey: boolean;
    spreadsheet_id: string;
    first_row: number;
    last_row: number;
    year: number;
    cron_expression: string;
};

type PlanImportProps = {
    imports: SheetImport[];
    selectedId: string | null;
    rows: SheetRow[];
    defaults: Defaults;
};

type DialogState =
    | { type: 'create' }
    | { type: 'edit'; sheetImport: SheetImport }
    | { type: 'delete'; sheetImport: SheetImport };

const columns: { field: ImportField; label: string }[] = [
    { field: 'service', label: 'Service' },
    { field: 'name', label: 'Event' },
    { field: 'comment', label: 'Comment' },
    { field: 'operator_1', label: 'Operator 1' },
    { field: 'operator_2', label: 'Operator 2' },
    { field: 'htl_1', label: 'HTL 1' },
    { field: 'htl_2', label: 'HTL 2' },
];

function ChangedValue({
    current,
    incoming,
}: {
    current: string | null;
    incoming: string | null;
}) {
    return (
        <Stack spacing={0.5}>
            <Typography
                variant="body2"
                sx={{ color: 'text.secondary', textDecoration: 'line-through' }}
            >
                {current ?? '—'}
            </Typography>
            <Typography variant="body2" sx={{ fontWeight: 700 }}>
                {incoming ?? '—'}
            </Typography>
        </Stack>
    );
}

function ImportDialog({
    sheetImport,
    defaults,
    onClose,
}: {
    sheetImport: SheetImport | null;
    defaults: Defaults;
    onClose: () => void;
}) {
    const http = useHttp<
        { api_key: string; spreadsheet_id: string; import_id: string },
        { sheet_names: string[] }
    >({
        api_key: '',
        spreadsheet_id: sheetImport?.spreadsheet_id ?? defaults.spreadsheet_id,
        import_id: sheetImport?.id ?? '',
    });
    const [tabs, setTabs] = useState<string[]>(sheetImport?.sheet_names ?? []);
    const [sheetName, setSheetName] = useState(sheetImport?.sheet_name ?? '');
    const [year, setYear] = useState(
        String(sheetImport?.year ?? defaults.year),
    );

    const hasSavedKey = sheetImport?.hasApiKey ?? defaults.hasApiKey;
    const tabsError = (http.errors as Record<string, string | undefined>).sheet;

    const loadTabs = () => {
        http.clearErrors();
        http.post(PlanImportTabsController().url, {
            onSuccess: (response) => {
                setTabs(response.sheet_names);

                if (!response.sheet_names.includes(sheetName)) {
                    setSheetName('');
                }
            },
        }).catch(() => undefined);
    };

    const selectSheet = (name: string) => {
        setSheetName(name);

        const yearInName = name.match(/\d{4}/)?.[0];

        if (yearInName) {
            setYear(yearInName);
        }
    };

    const formProps =
        sheetImport === null ? store.form() : update.form(sheetImport.id);

    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="md">
            <Form {...formProps} onSuccess={onClose}>
                {({ errors, processing }) => (
                    <>
                        <DialogTitle>
                            {sheetImport === null
                                ? 'Add import'
                                : `Edit import of ${sheetImport.sheet_name}`}
                        </DialogTitle>
                        <DialogContent>
                            <Stack spacing={2} sx={{ pt: 1 }}>
                                {(errors.sheet || tabsError) && (
                                    <Alert severity="error">
                                        {errors.sheet ?? tabsError}
                                    </Alert>
                                )}
                                <Stack
                                    direction={{ xs: 'column', md: 'row' }}
                                    spacing={2}
                                    sx={{ alignItems: { md: 'flex-start' } }}
                                >
                                    <TextField
                                        name="api_key"
                                        type="password"
                                        label="Google API key"
                                        value={http.data.api_key}
                                        onChange={(event) =>
                                            http.setData(
                                                'api_key',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="off"
                                        size="small"
                                        fullWidth
                                        required={!hasSavedKey}
                                        error={Boolean(
                                            errors.api_key ??
                                            http.errors.api_key,
                                        )}
                                        helperText={
                                            errors.api_key ??
                                            http.errors.api_key ??
                                            (hasSavedKey
                                                ? 'Leave empty to use the saved key.'
                                                : undefined)
                                        }
                                    />
                                    <TextField
                                        name="spreadsheet_id"
                                        label="Sheet id"
                                        value={http.data.spreadsheet_id}
                                        onChange={(event) =>
                                            http.setData(
                                                'spreadsheet_id',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="off"
                                        size="small"
                                        fullWidth
                                        required
                                        error={Boolean(
                                            errors.spreadsheet_id ??
                                            http.errors.spreadsheet_id,
                                        )}
                                        helperText={
                                            errors.spreadsheet_id ??
                                            http.errors.spreadsheet_id ??
                                            'The id or the whole URL of the spreadsheet.'
                                        }
                                    />
                                    <Button
                                        variant="outlined"
                                        onClick={loadTabs}
                                        disabled={http.processing}
                                        sx={{
                                            flexShrink: 0,
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        Load tabs
                                    </Button>
                                </Stack>
                                <Stack
                                    direction={{ xs: 'column', md: 'row' }}
                                    spacing={2}
                                    sx={{ alignItems: { md: 'flex-start' } }}
                                >
                                    <TextField
                                        select
                                        name="sheet_name"
                                        label="Month tab"
                                        value={sheetName}
                                        onChange={(event) =>
                                            selectSheet(event.target.value)
                                        }
                                        size="small"
                                        fullWidth
                                        required
                                        error={Boolean(errors.sheet_name)}
                                        helperText={
                                            errors.sheet_name ??
                                            (tabs.length === 0
                                                ? 'Load the tabs first.'
                                                : undefined)
                                        }
                                        slotProps={{
                                            select: { native: true },
                                            inputLabel: { shrink: true },
                                        }}
                                    >
                                        <option value="" disabled>
                                            Select a tab
                                        </option>
                                        {tabs.map((name) => (
                                            <option key={name} value={name}>
                                                {name}
                                            </option>
                                        ))}
                                    </TextField>
                                    <TextField
                                        name="first_row"
                                        type="number"
                                        label="Rows from"
                                        defaultValue={
                                            sheetImport?.first_row ??
                                            defaults.first_row
                                        }
                                        size="small"
                                        required
                                        error={Boolean(errors.first_row)}
                                        helperText={errors.first_row}
                                        sx={{ minWidth: 120 }}
                                    />
                                    <TextField
                                        name="last_row"
                                        type="number"
                                        label="Rows to"
                                        defaultValue={
                                            sheetImport?.last_row ??
                                            defaults.last_row
                                        }
                                        size="small"
                                        required
                                        error={Boolean(errors.last_row)}
                                        helperText={errors.last_row}
                                        sx={{ minWidth: 120 }}
                                    />
                                    <TextField
                                        name="year"
                                        type="number"
                                        label="Year"
                                        value={year}
                                        onChange={(event) =>
                                            setYear(event.target.value)
                                        }
                                        size="small"
                                        required
                                        error={Boolean(errors.year)}
                                        helperText={
                                            errors.year ??
                                            'Sheet dates have no year.'
                                        }
                                        sx={{ minWidth: 120 }}
                                    />
                                </Stack>
                                <Stack
                                    direction={{ xs: 'column', md: 'row' }}
                                    spacing={2}
                                    sx={{ alignItems: { md: 'flex-start' } }}
                                >
                                    <FormControlLabel
                                        control={
                                            <Switch
                                                name="auto_import"
                                                value="1"
                                                defaultChecked={
                                                    sheetImport?.auto_import ??
                                                    false
                                                }
                                            />
                                        }
                                        label="Import automatically (cron)"
                                        sx={{ flexShrink: 0, minHeight: 40 }}
                                    />
                                    <TextField
                                        name="cron_expression"
                                        label="Cron expression"
                                        defaultValue={
                                            sheetImport?.cron_expression ??
                                            defaults.cron_expression
                                        }
                                        size="small"
                                        required
                                        autoComplete="off"
                                        error={Boolean(errors.cron_expression)}
                                        helperText={
                                            errors.cron_expression ??
                                            'Minute hour day month weekday, e.g. 0 * * * * is hourly.'
                                        }
                                        sx={{ minWidth: 280 }}
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
                                {sheetImport === null ? 'Create' : 'Save'}
                            </Button>
                        </DialogActions>
                    </>
                )}
            </Form>
        </Dialog>
    );
}

export default function PlanImport({
    imports,
    selectedId,
    rows,
    defaults,
}: PlanImportProps) {
    const { errors } = usePage().props;
    const [dialog, setDialog] = useState<DialogState | null>(null);
    const [running, setRunning] = useState<string | null>(null);
    const selected = imports.find((item) => item.id === selectedId) ?? null;
    const changedCount = rows.filter((row) => row.hasChanges).length;

    const run = (id: string) =>
        router.post(
            PlanImportRunController(id).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setRunning(id),
                onFinish: () => setRunning(null),
            },
        );

    return (
        <>
            <Head title="Import plan" />
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
                        Import plan from Google Sheets
                    </Typography>
                    <Stack direction="row" spacing={1}>
                        <Button
                            variant="contained"
                            startIcon={<AddIcon />}
                            onClick={() => setDialog({ type: 'create' })}
                        >
                            Add import
                        </Button>
                    </Stack>
                </Stack>

                {errors.sheet && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {errors.sheet}
                    </Alert>
                )}

                <TableContainer component={Paper} variant="outlined">
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Tab</TableCell>
                                <TableCell>Sheet id</TableCell>
                                <TableCell>Rows</TableCell>
                                <TableCell>Year</TableCell>
                                <TableCell>Schedule</TableCell>
                                <TableCell>Last import</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {imports.map((item) => (
                                <TableRow
                                    key={item.id}
                                    hover
                                    selected={item.id === selectedId}
                                >
                                    <TableCell>
                                        {item.sheet_name}
                                        {item.changedCount > 0 && (
                                            <Chip
                                                icon={<WarningAmberIcon />}
                                                label={`${item.changedCount} changed`}
                                                color="warning"
                                                size="small"
                                                sx={{ ml: 1 }}
                                            />
                                        )}
                                    </TableCell>
                                    <TableCell
                                        sx={{
                                            maxWidth: 160,
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                        }}
                                    >
                                        {item.spreadsheet_id}
                                    </TableCell>
                                    <TableCell sx={{ whiteSpace: 'nowrap' }}>
                                        {item.first_row}–{item.last_row}
                                    </TableCell>
                                    <TableCell>{item.year}</TableCell>
                                    <TableCell sx={{ whiteSpace: 'nowrap' }}>
                                        {item.auto_import ? (
                                            <Chip
                                                label={item.cron_expression}
                                                size="small"
                                                color="primary"
                                                variant="outlined"
                                            />
                                        ) : (
                                            'Manual'
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {item.last_imported_at === null ? (
                                            <Typography
                                                variant="body2"
                                                sx={{ color: 'text.secondary' }}
                                            >
                                                Never
                                            </Typography>
                                        ) : (
                                            <Stack spacing={0.5}>
                                                <Typography variant="body2">
                                                    {new Date(
                                                        item.last_imported_at,
                                                    ).toLocaleString()}
                                                </Typography>
                                                <Chip
                                                    label={
                                                        item.last_import_message ??
                                                        item.last_import_status
                                                    }
                                                    color={
                                                        item.last_import_status ===
                                                        'failed'
                                                            ? 'error'
                                                            : 'success'
                                                    }
                                                    size="small"
                                                    variant="outlined"
                                                    sx={{
                                                        alignSelf: 'flex-start',
                                                        height: 'auto',
                                                        '& .MuiChip-label': {
                                                            whiteSpace:
                                                                'normal',
                                                        },
                                                    }}
                                                />
                                            </Stack>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        align="right"
                                        sx={{ whiteSpace: 'nowrap' }}
                                    >
                                        <Tooltip title="Show imported rows">
                                            <IconButton
                                                aria-label={`Show rows of ${item.sheet_name}`}
                                                onClick={() =>
                                                    router.get(
                                                        index({
                                                            query: {
                                                                import: item.id,
                                                            },
                                                        }).url,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <VisibilityIcon />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip title="Run again">
                                            <span>
                                                <IconButton
                                                    aria-label={`Run import of ${item.sheet_name}`}
                                                    disabled={running !== null}
                                                    onClick={() => run(item.id)}
                                                >
                                                    <PlayArrowIcon />
                                                </IconButton>
                                            </span>
                                        </Tooltip>
                                        <Tooltip title="Edit">
                                            <IconButton
                                                aria-label={`Edit import of ${item.sheet_name}`}
                                                onClick={() =>
                                                    setDialog({
                                                        type: 'edit',
                                                        sheetImport: item,
                                                    })
                                                }
                                            >
                                                <EditIcon />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip title="Delete">
                                            <IconButton
                                                aria-label={`Delete import of ${item.sheet_name}`}
                                                color="error"
                                                onClick={() =>
                                                    setDialog({
                                                        type: 'delete',
                                                        sheetImport: item,
                                                    })
                                                }
                                            >
                                                <DeleteIcon />
                                            </IconButton>
                                        </Tooltip>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {imports.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} align="center">
                                        No imports yet. Add one to load a month
                                        from Google Sheets.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>

                {selected !== null && (
                    <>
                        <Stack
                            direction="row"
                            spacing={1}
                            sx={{
                                mt: 3,
                                mb: 1,
                                alignItems: 'center',
                                justifyContent: 'space-between',
                            }}
                        >
                            <Typography variant="h6" component="h2">
                                Rows of {selected.sheet_name}
                                {changedCount > 0 && (
                                    <Chip
                                        icon={<WarningAmberIcon />}
                                        label={`${changedCount} changed in the sheet`}
                                        color="warning"
                                        size="small"
                                        sx={{ ml: 1 }}
                                    />
                                )}
                            </Typography>
                            {changedCount > 0 && (
                                <Button
                                    startIcon={<DoneAllIcon />}
                                    onClick={() =>
                                        router.post(
                                            acceptAll(selected.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Accept all changes
                                </Button>
                            )}
                        </Stack>

                        <TableContainer component={Paper} variant="outlined">
                            <Table size="small">
                                <TableHead>
                                    <TableRow>
                                        <TableCell>Row</TableCell>
                                        <TableCell>Date</TableCell>
                                        {columns.map((column) => (
                                            <TableCell key={column.field}>
                                                {column.label}
                                            </TableCell>
                                        ))}
                                        <TableCell align="right">
                                            Check
                                        </TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {rows.map((row) => (
                                        <TableRow
                                            key={row.id}
                                            hover
                                            sx={
                                                row.hasChanges
                                                    ? {
                                                          bgcolor:
                                                              'warning.light',
                                                      }
                                                    : undefined
                                            }
                                        >
                                            <TableCell>
                                                {row.row_number}
                                            </TableCell>
                                            <TableCell
                                                sx={{ whiteSpace: 'nowrap' }}
                                            >
                                                {row.date}
                                                {row.weekday &&
                                                    ` (${row.weekday})`}
                                            </TableCell>
                                            {columns.map((column) => (
                                                <TableCell key={column.field}>
                                                    {column.field in
                                                    row.changed_fields ? (
                                                        <ChangedValue
                                                            current={
                                                                row[
                                                                    column.field
                                                                ]
                                                            }
                                                            incoming={
                                                                row
                                                                    .changed_fields[
                                                                    column.field
                                                                ] ?? null
                                                            }
                                                        />
                                                    ) : (
                                                        (row[column.field] ??
                                                        '—')
                                                    )}
                                                </TableCell>
                                            ))}
                                            <TableCell
                                                align="right"
                                                sx={{ whiteSpace: 'nowrap' }}
                                            >
                                                {row.hasChanges ? (
                                                    <Stack
                                                        direction="row"
                                                        spacing={0.5}
                                                        sx={{
                                                            alignItems:
                                                                'center',
                                                            justifyContent:
                                                                'flex-end',
                                                        }}
                                                    >
                                                        <Chip
                                                            icon={
                                                                <WarningAmberIcon />
                                                            }
                                                            label="Changed"
                                                            color="warning"
                                                            size="small"
                                                        />
                                                        <Tooltip title="Accept the sheet values">
                                                            <IconButton
                                                                aria-label={`Accept changes of ${row.date}`}
                                                                size="small"
                                                                onClick={() =>
                                                                    router.post(
                                                                        accept(
                                                                            row.id,
                                                                        ).url,
                                                                        {},
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <CheckIcon />
                                                            </IconButton>
                                                        </Tooltip>
                                                    </Stack>
                                                ) : (
                                                    <Chip
                                                        label="Same"
                                                        color="success"
                                                        size="small"
                                                        variant="outlined"
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {rows.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={columns.length + 3}
                                                align="center"
                                            >
                                                Nothing has been imported from
                                                this tab yet. Run the import.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </TableContainer>
                    </>
                )}
            </Box>

            {dialog?.type === 'create' && (
                <ImportDialog
                    sheetImport={null}
                    defaults={defaults}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.type === 'edit' && (
                <ImportDialog
                    sheetImport={dialog.sheetImport}
                    defaults={defaults}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.type === 'delete' && (
                <ConfirmDeleteDialog
                    title="Delete import"
                    form={destroy.form(dialog.sheetImport.id)}
                    onClose={() => setDialog(null)}
                >
                    Delete the import of {dialog.sheetImport.sheet_name}? The
                    rows it imported and the plan calendar stay as they are.
                </ConfirmDeleteDialog>
            )}

            <FlashSnackbar autoHideDuration={6000} />
        </>
    );
}
