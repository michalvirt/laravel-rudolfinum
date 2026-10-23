import { Form, Head, router } from '@inertiajs/react';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import SendIcon from '@mui/icons-material/Send';
import VisibilityIcon from '@mui/icons-material/Visibility';
import {
    Box,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
    List,
    ListItem,
    ListItemText,
    Paper,
    Stack,
    Switch,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TablePagination,
    TableRow,
    TextField,
    ToggleButton,
    ToggleButtonGroup,
    Tooltip,
    Typography,
} from '@mui/material';
import { useState } from 'react';
import EmailNotificationActionSettingController from '@/actions/App/Http/Controllers/EmailNotificationActionSettingController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import FlashSnackbar from '@/components/flash-snackbar';
import {
    destroy,
    index,
    send,
    sendFailed,
    sendPending,
} from '@/routes/email-notifications';

type NotificationState = 'sent' | 'failed' | 'pending';

type Notification = {
    id: string;
    action: string;
    receiver_email: string;
    subject: string;
    body: string;
    language: string;
    state: NotificationState;
    attempts: number;
    error_message: string | null;
    created_at: string | null;
    last_attempt_at: string | null;
};

type ActionSetting = {
    id: string;
    action: string;
    label: string;
    background_color: string;
    font_color: string;
    send_mode: string;
    language: string;
    batch_interval_minutes: number;
    last_batch_sent_at: string | null;
    receiver_permission: string;
    is_enabled: boolean;
};

type EmailNotificationsProps = {
    notifications: Notification[];
    pagination: { page: number; perPage: number; total: number };
    state: 'all' | NotificationState;
    counts: Record<NotificationState, number>;
    actionSettings: ActionSetting[];
    options: {
        sendModes: string[];
        languages: string[];
        permissions: string[];
    };
};

type DialogState =
    | { type: 'view'; notification: Notification }
    | { type: 'edit'; actionSetting: ActionSetting }
    | { type: 'delete'; notification: Notification };

const stateColors: Record<NotificationState, 'success' | 'error' | 'default'> =
    {
        sent: 'success',
        failed: 'error',
        pending: 'default',
    };

const sendModeLabels: Record<string, string> = {
    immediate: 'Immediately after the action',
    batch: 'In a batch every few minutes',
};

const languageLabels: Record<string, string> = {
    en: 'English',
    cs: 'Czech',
};

function formatDate(value: string | null): string {
    return value === null ? '-' : new Date(value).toLocaleString();
}

function ActionChip({
    actionSetting,
    fallback,
}: {
    actionSetting: ActionSetting | undefined;
    fallback: string;
}) {
    return (
        <Chip
            size="small"
            label={actionSetting?.label ?? fallback}
            sx={{
                bgcolor: actionSetting?.background_color,
                color: actionSetting?.font_color,
            }}
        />
    );
}

function describeSending(actionSetting: ActionSetting): string {
    return actionSetting.send_mode === 'batch'
        ? `In a batch every ${actionSetting.batch_interval_minutes} min`
        : (sendModeLabels[actionSetting.send_mode] ?? actionSetting.send_mode);
}

function ActionSettingsList({
    actionSettings,
    onEdit,
}: {
    actionSettings: ActionSetting[];
    onEdit: (actionSetting: ActionSetting) => void;
}) {
    const toggle = (actionSetting: ActionSetting, isEnabled: boolean) =>
        router.put(
            EmailNotificationActionSettingController(actionSetting.id).url,
            {
                label: actionSetting.label,
                background_color: actionSetting.background_color,
                font_color: actionSetting.font_color,
                send_mode: actionSetting.send_mode,
                language: actionSetting.language,
                batch_interval_minutes: actionSetting.batch_interval_minutes,
                receiver_permission: actionSetting.receiver_permission,
                is_enabled: isEnabled,
            },
            { preserveScroll: true },
        );

    return (
        <Paper variant="outlined" sx={{ mb: 2 }}>
            <Typography variant="h6" component="h2" sx={{ p: 2, pb: 0 }}>
                Notifications
            </Typography>
            <List>
                {actionSettings.map((actionSetting) => (
                    <ListItem
                        key={actionSetting.id}
                        divider
                        sx={{ gap: 2, flexWrap: 'wrap' }}
                        secondaryAction={
                            <Tooltip title="Edit">
                                <IconButton
                                    edge="end"
                                    aria-label={`Edit ${actionSetting.label}`}
                                    onClick={() => onEdit(actionSetting)}
                                >
                                    <EditIcon />
                                </IconButton>
                            </Tooltip>
                        }
                    >
                        <Switch
                            checked={actionSetting.is_enabled}
                            onChange={(event) =>
                                toggle(actionSetting, event.target.checked)
                            }
                            slotProps={{
                                input: {
                                    'aria-label': `Enable ${actionSetting.label}`,
                                },
                            }}
                        />
                        <Box sx={{ minWidth: 200 }}>
                            <ActionChip
                                actionSetting={actionSetting}
                                fallback={actionSetting.action}
                            />
                        </Box>
                        <ListItemText
                            sx={{ flex: '0 1 auto', minWidth: 220 }}
                            primary={describeSending(actionSetting)}
                            secondary={
                                actionSetting.send_mode === 'batch'
                                    ? `Last batch sent: ${formatDate(actionSetting.last_batch_sent_at)}`
                                    : undefined
                            }
                        />
                        <Typography sx={{ minWidth: 100 }}>
                            {languageLabels[actionSetting.language] ??
                                actionSetting.language}
                        </Typography>
                    </ListItem>
                ))}
            </List>
        </Paper>
    );
}

function ActionSettingDialog({
    actionSetting,
    options,
    onClose,
}: {
    actionSetting: ActionSetting;
    options: EmailNotificationsProps['options'];
    onClose: () => void;
}) {
    const [label, setLabel] = useState(actionSetting.label);
    const [backgroundColor, setBackgroundColor] = useState(
        actionSetting.background_color,
    );
    const [fontColor, setFontColor] = useState(actionSetting.font_color);
    const [sendMode, setSendMode] = useState(actionSetting.send_mode);
    const [isEnabled, setIsEnabled] = useState(actionSetting.is_enabled);

    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="sm">
            <Form
                {...EmailNotificationActionSettingController.form(
                    actionSetting.id,
                )}
                onSuccess={onClose}
            >
                {({ errors, processing }) => (
                    <>
                        <DialogTitle>Edit notification</DialogTitle>
                        <DialogContent>
                            <Stack spacing={2} sx={{ pt: 1 }}>
                                <TextField
                                    name="label"
                                    label="Name of the action"
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
                                        size="small"
                                        label={label || 'Name'}
                                        sx={{
                                            bgcolor: backgroundColor,
                                            color: fontColor,
                                        }}
                                    />
                                </Stack>
                                <TextField
                                    select
                                    name="send_mode"
                                    label="Sending"
                                    value={sendMode}
                                    onChange={(event) =>
                                        setSendMode(event.target.value)
                                    }
                                    slotProps={{ select: { native: true } }}
                                    error={Boolean(errors.send_mode)}
                                    helperText={errors.send_mode}
                                >
                                    {options.sendModes.map((mode) => (
                                        <option key={mode} value={mode}>
                                            {sendModeLabels[mode] ?? mode}
                                        </option>
                                    ))}
                                </TextField>
                                <TextField
                                    name="batch_interval_minutes"
                                    type="number"
                                    label="Batch interval (minutes)"
                                    defaultValue={
                                        actionSetting.batch_interval_minutes
                                    }
                                    disabled={sendMode !== 'batch'}
                                    slotProps={{
                                        htmlInput: { min: 1, max: 1440 },
                                    }}
                                    error={Boolean(
                                        errors.batch_interval_minutes,
                                    )}
                                    helperText={errors.batch_interval_minutes}
                                />
                                {sendMode !== 'batch' && (
                                    <input
                                        type="hidden"
                                        name="batch_interval_minutes"
                                        value={
                                            actionSetting.batch_interval_minutes
                                        }
                                    />
                                )}
                                <TextField
                                    select
                                    name="language"
                                    label="Template language"
                                    defaultValue={actionSetting.language}
                                    slotProps={{ select: { native: true } }}
                                    error={Boolean(errors.language)}
                                    helperText={errors.language}
                                >
                                    {options.languages.map((language) => (
                                        <option key={language} value={language}>
                                            {languageLabels[language] ??
                                                language}
                                        </option>
                                    ))}
                                </TextField>
                                <TextField
                                    select
                                    name="receiver_permission"
                                    label="Receiver permission"
                                    defaultValue={
                                        actionSetting.receiver_permission
                                    }
                                    slotProps={{ select: { native: true } }}
                                    error={Boolean(errors.receiver_permission)}
                                    helperText={
                                        errors.receiver_permission ??
                                        'Users whose permission group has this permission receive the notification'
                                    }
                                >
                                    {options.permissions.map((permission) => (
                                        <option
                                            key={permission}
                                            value={permission}
                                        >
                                            {permission}
                                        </option>
                                    ))}
                                </TextField>
                                <input
                                    type="hidden"
                                    name="is_enabled"
                                    value={isEnabled ? '1' : '0'}
                                />
                                <FormControlLabel
                                    control={
                                        <Switch
                                            checked={isEnabled}
                                            onChange={(event) =>
                                                setIsEnabled(
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                    }
                                    label="Enabled"
                                />
                            </Stack>
                        </DialogContent>
                        <DialogActions>
                            <Button onClick={onClose}>Cancel</Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={processing}
                            >
                                Save
                            </Button>
                        </DialogActions>
                    </>
                )}
            </Form>
        </Dialog>
    );
}

function NotificationBodyDialog({
    notification,
    onClose,
}: {
    notification: Notification;
    onClose: () => void;
}) {
    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="sm">
            <DialogTitle>{notification.subject}</DialogTitle>
            <DialogContent>
                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                    To: {notification.receiver_email}
                </Typography>
                <Typography
                    component="pre"
                    sx={{
                        mt: 2,
                        fontFamily: 'inherit',
                        whiteSpace: 'pre-wrap',
                    }}
                >
                    {notification.body}
                </Typography>
                {notification.error_message && (
                    <Typography color="error" sx={{ mt: 2 }}>
                        Error: {notification.error_message}
                    </Typography>
                )}
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Close</Button>
            </DialogActions>
        </Dialog>
    );
}

export default function EmailNotifications({
    notifications,
    pagination,
    state,
    counts,
    actionSettings,
    options,
}: EmailNotificationsProps) {
    const [dialog, setDialog] = useState<DialogState | null>(null);
    const closeDialog = () => setDialog(null);

    const visit = (query: { state?: string; page?: number }) =>
        router.get(index().url, query, { preserveState: true });

    return (
        <>
            <Head title="Email notifications" />
            <Box sx={{ maxWidth: 1600, mx: 'auto', p: 2 }}>
                <Typography variant="h5" component="h1" sx={{ mb: 2 }}>
                    Email notifications
                </Typography>

                <ActionSettingsList
                    actionSettings={actionSettings}
                    onEdit={(actionSetting) =>
                        setDialog({ type: 'edit', actionSetting })
                    }
                />

                <Stack
                    direction="row"
                    spacing={1}
                    sx={{
                        mb: 2,
                        flexWrap: 'wrap',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    <ToggleButtonGroup
                        exclusive
                        size="small"
                        value={state}
                        aria-label="Filter by sending state"
                        onChange={(_event, value: string | null) =>
                            value !== null && visit({ state: value })
                        }
                    >
                        <ToggleButton value="all">All</ToggleButton>
                        <ToggleButton value="sent">
                            Sent ({counts.sent})
                        </ToggleButton>
                        <ToggleButton value="failed">
                            Failed ({counts.failed})
                        </ToggleButton>
                        <ToggleButton value="pending">
                            Pending ({counts.pending})
                        </ToggleButton>
                    </ToggleButtonGroup>
                    <Stack direction="row" spacing={1}>
                        <Button
                            variant="outlined"
                            disabled={counts.pending === 0}
                            onClick={() =>
                                router.post(
                                    sendPending().url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Send pending now
                        </Button>
                        <Button
                            variant="outlined"
                            color="error"
                            disabled={counts.failed === 0}
                            onClick={() =>
                                router.post(
                                    sendFailed().url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Resend all failed
                        </Button>
                    </Stack>
                </Stack>

                <TableContainer component={Paper} variant="outlined">
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableCell>Created</TableCell>
                                <TableCell>Action</TableCell>
                                <TableCell>Receiver</TableCell>
                                <TableCell>Subject</TableCell>
                                <TableCell>State</TableCell>
                                <TableCell>Last attempt</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {notifications.map((notification) => (
                                <TableRow key={notification.id} hover>
                                    <TableCell sx={{ whiteSpace: 'nowrap' }}>
                                        {formatDate(notification.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <ActionChip
                                            actionSetting={actionSettings.find(
                                                (actionSetting) =>
                                                    actionSetting.action ===
                                                    notification.action,
                                            )}
                                            fallback={notification.action}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        {notification.receiver_email}
                                    </TableCell>
                                    <TableCell>
                                        {notification.subject}
                                    </TableCell>
                                    <TableCell>
                                        <Tooltip
                                            title={
                                                notification.error_message ?? ''
                                            }
                                        >
                                            <Chip
                                                label={notification.state}
                                                size="small"
                                                color={
                                                    stateColors[
                                                        notification.state
                                                    ]
                                                }
                                            />
                                        </Tooltip>
                                    </TableCell>
                                    <TableCell sx={{ whiteSpace: 'nowrap' }}>
                                        {formatDate(
                                            notification.last_attempt_at,
                                        )}
                                        {notification.attempts > 0 &&
                                            ` (${notification.attempts}x)`}
                                    </TableCell>
                                    <TableCell
                                        align="right"
                                        sx={{ whiteSpace: 'nowrap' }}
                                    >
                                        <Tooltip title="Show the email">
                                            <IconButton
                                                aria-label={`Show ${notification.subject}`}
                                                onClick={() =>
                                                    setDialog({
                                                        type: 'view',
                                                        notification,
                                                    })
                                                }
                                            >
                                                <VisibilityIcon />
                                            </IconButton>
                                        </Tooltip>
                                        {notification.state !== 'sent' && (
                                            <Tooltip
                                                title={
                                                    notification.state ===
                                                    'failed'
                                                        ? 'Resend'
                                                        : 'Send now'
                                                }
                                            >
                                                <IconButton
                                                    aria-label={`Send ${notification.subject}`}
                                                    color="primary"
                                                    onClick={() =>
                                                        router.post(
                                                            send(
                                                                notification.id,
                                                            ).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <SendIcon />
                                                </IconButton>
                                            </Tooltip>
                                        )}
                                        <Tooltip title="Delete">
                                            <IconButton
                                                aria-label={`Delete ${notification.subject}`}
                                                color="error"
                                                onClick={() =>
                                                    setDialog({
                                                        type: 'delete',
                                                        notification,
                                                    })
                                                }
                                            >
                                                <DeleteIcon />
                                            </IconButton>
                                        </Tooltip>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {notifications.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} align="center">
                                        No notifications yet.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                    <TablePagination
                        component="div"
                        count={pagination.total}
                        page={pagination.page - 1}
                        rowsPerPage={pagination.perPage}
                        rowsPerPageOptions={[]}
                        onPageChange={(_event, page) =>
                            visit({ state, page: page + 1 })
                        }
                    />
                </TableContainer>
            </Box>

            {dialog?.type === 'view' && (
                <NotificationBodyDialog
                    notification={dialog.notification}
                    onClose={closeDialog}
                />
            )}
            {dialog?.type === 'edit' && (
                <ActionSettingDialog
                    key={dialog.actionSetting.id}
                    actionSetting={dialog.actionSetting}
                    options={options}
                    onClose={closeDialog}
                />
            )}
            {dialog?.type === 'delete' && (
                <ConfirmDeleteDialog
                    title="Delete notification"
                    form={destroy.form(dialog.notification.id)}
                    onClose={closeDialog}
                >
                    Delete the notification for{' '}
                    {dialog.notification.receiver_email}? This cannot be undone.
                </ConfirmDeleteDialog>
            )}

            <FlashSnackbar />
        </>
    );
}
