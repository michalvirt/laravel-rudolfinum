import { Form, Head } from '@inertiajs/react';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import LockResetIcon from '@mui/icons-material/LockReset';
import {
    Box,
    Button,
    Checkbox,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
    FormControlLabel,
    FormGroup,
    FormHelperText,
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
} from '@/actions/App/Http/Controllers/AccountController';
import { update as updatePassword } from '@/actions/App/Http/Controllers/AccountPasswordController';
import PermissionGroupNotificationPermissionController from '@/actions/App/Http/Controllers/PermissionGroupNotificationPermissionController';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import FlashSnackbar from '@/components/flash-snackbar';

type Account = {
    id: string;
    name: string;
    surname: string;
    email: string;
    phone: string | null;
    permission_group_id: string | null;
    permission_group: string | null;
    notification_permissions: string[];
    isCurrentUser: boolean;
};

type PermissionGroup = {
    id: string;
    name: string;
    notification_permissions: string[];
};

type NotificationPermission = {
    value: string;
    label: string;
};

type AccountsProps = {
    accounts: Account[];
    permissionGroups: PermissionGroup[];
    notificationPermissions: NotificationPermission[];
};

type DialogState =
    | { type: 'create' }
    | { type: 'edit'; account: Account }
    | { type: 'password'; account: Account }
    | { type: 'group'; group: PermissionGroup }
    | { type: 'delete'; account: Account };

type DialogProps = {
    onClose: () => void;
};

function notificationLabels(
    values: string[],
    notificationPermissions: NotificationPermission[],
): string[] {
    return notificationPermissions
        .filter((permission) => values.includes(permission.value))
        .map((permission) => permission.label);
}

function NotificationChips({ labels }: { labels: string[] }) {
    if (labels.length === 0) {
        return (
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                —
            </Typography>
        );
    }

    return (
        <Stack
            direction="row"
            spacing={0.5}
            useFlexGap
            sx={{ flexWrap: 'wrap' }}
        >
            {labels.map((label) => (
                <Chip key={label} label={label} size="small" />
            ))}
        </Stack>
    );
}

function fullName(account: Account): string {
    return `${account.name} ${account.surname}`;
}

function AccountDialog({
    account,
    permissionGroups,
    notificationPermissions,
    onClose,
}: DialogProps & {
    account: Account | null;
    permissionGroups: PermissionGroup[];
    notificationPermissions: NotificationPermission[];
}) {
    const formProps = account === null ? store.form() : update.form(account.id);
    const [groupId, setGroupId] = useState(account?.permission_group_id ?? '');
    const group = permissionGroups.find((item) => item.id === groupId);

    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="sm">
            <Form {...formProps} onSuccess={onClose}>
                {({ errors, processing }) => (
                    <>
                        <DialogTitle>
                            {account === null ? 'Add account' : 'Edit account'}
                        </DialogTitle>
                        <DialogContent>
                            <Stack spacing={2} sx={{ pt: 1 }}>
                                <TextField
                                    name="name"
                                    label="Name"
                                    defaultValue={account?.name}
                                    autoComplete="off"
                                    autoFocus
                                    required
                                    error={Boolean(errors.name)}
                                    helperText={errors.name}
                                />
                                <TextField
                                    name="surname"
                                    label="Surname"
                                    defaultValue={account?.surname}
                                    autoComplete="off"
                                    required
                                    error={Boolean(errors.surname)}
                                    helperText={errors.surname}
                                />
                                <TextField
                                    name="email"
                                    type="email"
                                    label="Email"
                                    defaultValue={account?.email}
                                    autoComplete="off"
                                    required
                                    error={Boolean(errors.email)}
                                    helperText={errors.email}
                                />
                                <TextField
                                    name="phone"
                                    label="Phone"
                                    defaultValue={account?.phone ?? ''}
                                    autoComplete="off"
                                    error={Boolean(errors.phone)}
                                    helperText={errors.phone}
                                />
                                <TextField
                                    select
                                    name="permission_group_id"
                                    label="Permission group"
                                    value={groupId}
                                    onChange={(event) =>
                                        setGroupId(event.target.value)
                                    }
                                    error={Boolean(errors.permission_group_id)}
                                    helperText={errors.permission_group_id}
                                    slotProps={{
                                        select: { native: true },
                                        inputLabel: { shrink: true },
                                    }}
                                >
                                    <option value="">No group</option>
                                    {permissionGroups.map((group) => (
                                        <option key={group.id} value={group.id}>
                                            {group.name}
                                        </option>
                                    ))}
                                </TextField>
                                <NotificationPermissionFields
                                    notificationPermissions={
                                        notificationPermissions
                                    }
                                    selected={
                                        account?.notification_permissions ?? []
                                    }
                                    groupGrants={
                                        group?.notification_permissions ?? []
                                    }
                                    groupName={group?.name}
                                    error={errors.permissions}
                                />
                                {account === null && (
                                    <PasswordFields errors={errors} />
                                )}
                            </Stack>
                        </DialogContent>
                        <DialogActions>
                            <Button onClick={onClose}>Cancel</Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={processing}
                            >
                                {account === null ? 'Create' : 'Save'}
                            </Button>
                        </DialogActions>
                    </>
                )}
            </Form>
        </Dialog>
    );
}

function NotificationPermissionFields({
    notificationPermissions,
    selected,
    groupGrants = [],
    groupName,
    error,
}: {
    notificationPermissions: NotificationPermission[];
    selected: string[];
    groupGrants?: string[];
    groupName?: string;
    error?: string;
}) {
    return (
        <FormGroup>
            <Typography variant="subtitle2">Notifications</Typography>
            {notificationPermissions.map((permission) => (
                <FormControlLabel
                    key={permission.value}
                    control={
                        <Checkbox
                            name="permissions[]"
                            value={permission.value}
                            defaultChecked={selected.includes(permission.value)}
                        />
                    }
                    label={
                        <>
                            {permission.label}
                            {groupGrants.includes(permission.value) && (
                                <Typography
                                    variant="caption"
                                    component="span"
                                    sx={{ ml: 1, color: 'text.secondary' }}
                                >
                                    also granted by {groupName}, one email is
                                    sent
                                </Typography>
                            )}
                        </>
                    }
                />
            ))}
            {error && <FormHelperText error>{error}</FormHelperText>}
        </FormGroup>
    );
}

function GroupDialog({
    group,
    notificationPermissions,
    onClose,
}: DialogProps & {
    group: PermissionGroup;
    notificationPermissions: NotificationPermission[];
}) {
    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="xs">
            <Form
                {...PermissionGroupNotificationPermissionController.form(
                    group.id,
                )}
                onSuccess={onClose}
            >
                {({ errors, processing }) => (
                    <>
                        <DialogTitle>Notifications of {group.name}</DialogTitle>
                        <DialogContent>
                            <Stack spacing={2} sx={{ pt: 1 }}>
                                <DialogContentText>
                                    Every user in this group receives these
                                    notifications. A user who also has the
                                    permission set directly still receives one
                                    email.
                                </DialogContentText>
                                <NotificationPermissionFields
                                    notificationPermissions={
                                        notificationPermissions
                                    }
                                    selected={group.notification_permissions}
                                    error={errors.permissions}
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

function PasswordFields({ errors }: { errors: Record<string, string> }) {
    return (
        <>
            <TextField
                name="password"
                type="password"
                label="Password"
                autoComplete="new-password"
                required
                error={Boolean(errors.password)}
                helperText={errors.password}
            />
            <TextField
                name="password_confirmation"
                type="password"
                label="Confirm password"
                autoComplete="new-password"
                required
            />
        </>
    );
}

function ResetPasswordDialog({
    account,
    onClose,
}: DialogProps & { account: Account }) {
    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="xs">
            <Form
                {...updatePassword.form(account.id)}
                onSuccess={onClose}
                resetOnError
            >
                {({ errors, processing }) => (
                    <>
                        <DialogTitle>Reset password</DialogTitle>
                        <DialogContent>
                            <Stack spacing={2} sx={{ pt: 1 }}>
                                <DialogContentText>
                                    Set a new password for {fullName(account)}.
                                    Their other sessions will be signed out.
                                </DialogContentText>
                                <PasswordFields errors={errors} />
                            </Stack>
                        </DialogContent>
                        <DialogActions>
                            <Button onClick={onClose}>Cancel</Button>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={processing}
                            >
                                Reset password
                            </Button>
                        </DialogActions>
                    </>
                )}
            </Form>
        </Dialog>
    );
}

export default function Accounts({
    accounts,
    permissionGroups,
    notificationPermissions,
}: AccountsProps) {
    const [dialog, setDialog] = useState<DialogState | null>(null);
    const [search, setSearch] = useState('');

    const closeDialog = () => setDialog(null);
    const query = search.trim().toLowerCase();
    const visibleAccounts = accounts.filter((account) =>
        [fullName(account), account.email, account.phone ?? '']
            .join(' ')
            .toLowerCase()
            .includes(query),
    );

    return (
        <>
            <Head title="Accounts" />
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
                        Accounts
                    </Typography>
                    <Stack direction="row" spacing={1}>
                        <Button
                            variant="contained"
                            startIcon={<AddIcon />}
                            onClick={() => setDialog({ type: 'create' })}
                        >
                            Add account
                        </Button>
                    </Stack>
                </Stack>

                <TextField
                    type="search"
                    label="Search accounts"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    size="small"
                    fullWidth
                    sx={{ mb: 2 }}
                />

                <TableContainer component={Paper} variant="outlined">
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Email</TableCell>
                                <TableCell>Phone</TableCell>
                                <TableCell>Permission group</TableCell>
                                <TableCell>Notifications</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {visibleAccounts.map((account) => (
                                <TableRow key={account.id} hover>
                                    <TableCell>
                                        {fullName(account)}
                                        {account.isCurrentUser && (
                                            <Chip
                                                label="You"
                                                size="small"
                                                sx={{ ml: 1 }}
                                            />
                                        )}
                                    </TableCell>
                                    <TableCell>{account.email}</TableCell>
                                    <TableCell>
                                        {account.phone ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {account.permission_group ?? (
                                            <Typography
                                                variant="body2"
                                                sx={{ color: 'text.secondary' }}
                                            >
                                                No group
                                            </Typography>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <NotificationChips
                                            labels={notificationLabels(
                                                account.notification_permissions,
                                                notificationPermissions,
                                            )}
                                        />
                                    </TableCell>
                                    <TableCell
                                        align="right"
                                        sx={{ whiteSpace: 'nowrap' }}
                                    >
                                        <Tooltip title="Edit">
                                            <IconButton
                                                aria-label={`Edit ${fullName(account)}`}
                                                onClick={() =>
                                                    setDialog({
                                                        type: 'edit',
                                                        account,
                                                    })
                                                }
                                            >
                                                <EditIcon />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip title="Reset password">
                                            <IconButton
                                                aria-label={`Reset password of ${fullName(account)}`}
                                                onClick={() =>
                                                    setDialog({
                                                        type: 'password',
                                                        account,
                                                    })
                                                }
                                            >
                                                <LockResetIcon />
                                            </IconButton>
                                        </Tooltip>
                                        <Tooltip
                                            title={
                                                account.isCurrentUser
                                                    ? 'You cannot delete your own account'
                                                    : 'Delete'
                                            }
                                        >
                                            <span>
                                                <IconButton
                                                    aria-label={`Delete ${fullName(account)}`}
                                                    color="error"
                                                    disabled={
                                                        account.isCurrentUser
                                                    }
                                                    onClick={() =>
                                                        setDialog({
                                                            type: 'delete',
                                                            account,
                                                        })
                                                    }
                                                >
                                                    <DeleteIcon />
                                                </IconButton>
                                            </span>
                                        </Tooltip>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {visibleAccounts.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} align="center">
                                        No accounts found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>

                <Typography variant="h6" component="h2" sx={{ mt: 4, mb: 1 }}>
                    Notifications per permission group
                </Typography>
                <TableContainer component={Paper} variant="outlined">
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableCell>Permission group</TableCell>
                                <TableCell>Notifications</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {permissionGroups.map((group) => (
                                <TableRow key={group.id} hover>
                                    <TableCell>{group.name}</TableCell>
                                    <TableCell>
                                        <NotificationChips
                                            labels={notificationLabels(
                                                group.notification_permissions,
                                                notificationPermissions,
                                            )}
                                        />
                                    </TableCell>
                                    <TableCell align="right">
                                        <Tooltip title="Edit notifications">
                                            <IconButton
                                                aria-label={`Edit notifications of ${group.name}`}
                                                onClick={() =>
                                                    setDialog({
                                                        type: 'group',
                                                        group,
                                                    })
                                                }
                                            >
                                                <EditIcon />
                                            </IconButton>
                                        </Tooltip>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {permissionGroups.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={3} align="center">
                                        No permission groups found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Box>

            {dialog?.type === 'create' && (
                <AccountDialog
                    account={null}
                    permissionGroups={permissionGroups}
                    notificationPermissions={notificationPermissions}
                    onClose={closeDialog}
                />
            )}
            {dialog?.type === 'edit' && (
                <AccountDialog
                    account={dialog.account}
                    permissionGroups={permissionGroups}
                    notificationPermissions={notificationPermissions}
                    onClose={closeDialog}
                />
            )}
            {dialog?.type === 'group' && (
                <GroupDialog
                    group={dialog.group}
                    notificationPermissions={notificationPermissions}
                    onClose={closeDialog}
                />
            )}
            {dialog?.type === 'password' && (
                <ResetPasswordDialog
                    account={dialog.account}
                    onClose={closeDialog}
                />
            )}
            {dialog?.type === 'delete' && (
                <ConfirmDeleteDialog
                    title="Delete account"
                    form={destroy.form(dialog.account.id)}
                    onClose={closeDialog}
                >
                    Delete the account of {fullName(dialog.account)} (
                    {dialog.account.email})? This cannot be undone.
                </ConfirmDeleteDialog>
            )}

            <FlashSnackbar />
        </>
    );
}
