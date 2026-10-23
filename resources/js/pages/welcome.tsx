import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Box,
    Button,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
    Stack,
    TextField,
} from '@mui/material';
import { update as updateProfile } from '@/actions/App/Http/Controllers/ProfileController';
import { update as updatePassword } from '@/actions/App/Http/Controllers/ProfilePasswordController';
import FlashSnackbar from '@/components/flash-snackbar';
import { login } from '@/routes';

export default function Welcome() {
    const { user } = usePage().props.auth;

    return (
        <>
            <Head title="Welcome" />
            <Box sx={{ maxWidth: 960, mx: 'auto', p: 2 }}>
                {user === null ? (
                    <Button
                        LinkComponent={Link}
                        href={login().url}
                        variant="contained"
                        sx={{ mt: 2 }}
                    >
                        Log in
                    </Button>
                ) : (
                    <Stack spacing={2} sx={{ mt: 2, alignItems: 'flex-start' }}>
                        <Stack
                            direction="row"
                            spacing={2}
                            useFlexGap
                            sx={{ flexWrap: 'wrap', alignItems: 'flex-end' }}
                        >
                            <Form {...updateProfile.form()}>
                                {({ errors, processing }) => (
                                    <>
                                        <DialogTitle>Edit profile</DialogTitle>
                                        <DialogContent>
                                            <Stack
                                                spacing={2}
                                                sx={{ pt: 1, width: 400 }}
                                            >
                                                <TextField
                                                    name="name"
                                                    label="Name"
                                                    defaultValue={user.name}
                                                    autoComplete="given-name"
                                                    autoFocus
                                                    required
                                                    error={Boolean(errors.name)}
                                                    helperText={errors.name}
                                                />
                                                <TextField
                                                    name="surname"
                                                    label="Surname"
                                                    defaultValue={user.surname}
                                                    autoComplete="family-name"
                                                    required
                                                    error={Boolean(
                                                        errors.surname,
                                                    )}
                                                    helperText={errors.surname}
                                                />
                                                <TextField
                                                    name="phone"
                                                    label="Phone"
                                                    defaultValue={
                                                        user.phone ?? ''
                                                    }
                                                    autoComplete="tel"
                                                    error={Boolean(
                                                        errors.phone,
                                                    )}
                                                    helperText={errors.phone}
                                                />
                                                <TextField
                                                    name="email"
                                                    type="email"
                                                    label="Email"
                                                    defaultValue={user.email}
                                                    autoComplete="email"
                                                    required
                                                    error={Boolean(
                                                        errors.email,
                                                    )}
                                                    helperText={errors.email}
                                                />
                                            </Stack>
                                        </DialogContent>
                                        <DialogActions
                                            sx={{ justifyContent: 'center' }}
                                        >
                                            <Button
                                                sx={{ width: 400 }}
                                                type="submit"
                                                variant="contained"
                                                disabled={processing}
                                                size="large"
                                            >
                                                Save
                                            </Button>
                                        </DialogActions>
                                    </>
                                )}
                            </Form>

                            <Form {...updatePassword.form()} resetOnError>
                                {({ errors, processing }) => (
                                    <>
                                        <DialogTitle>
                                            Change password
                                        </DialogTitle>
                                        <DialogContent>
                                            <Stack
                                                spacing={2}
                                                sx={{ pt: 1, width: 400 }}
                                            >
                                                <DialogContentText>
                                                    Your other sessions will be
                                                    signed out.
                                                </DialogContentText>
                                                <TextField
                                                    name="current_password"
                                                    type="password"
                                                    label="Current password"
                                                    autoComplete="current-password"
                                                    autoFocus
                                                    required
                                                    error={Boolean(
                                                        errors.current_password,
                                                    )}
                                                    helperText={
                                                        errors.current_password
                                                    }
                                                />
                                                <TextField
                                                    name="password"
                                                    type="password"
                                                    label="New password"
                                                    autoComplete="new-password"
                                                    required
                                                    error={Boolean(
                                                        errors.password,
                                                    )}
                                                    helperText={errors.password}
                                                />
                                                <TextField
                                                    name="password_confirmation"
                                                    type="password"
                                                    label="Confirm new password"
                                                    autoComplete="new-password"
                                                    required
                                                />
                                            </Stack>
                                        </DialogContent>
                                        <DialogActions
                                            sx={{ justifyContent: 'center' }}
                                        >
                                            <Button
                                                sx={{ width: 400 }}
                                                type="submit"
                                                variant="contained"
                                                disabled={processing}
                                                size="large"
                                            >
                                                Change password
                                            </Button>
                                        </DialogActions>
                                    </>
                                )}
                            </Form>
                        </Stack>
                    </Stack>
                )}
            </Box>
            <FlashSnackbar />
        </>
    );
}
