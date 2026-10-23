import { Form, Head } from '@inertiajs/react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    FormControlLabel,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { store } from '@/actions/App/Http/Controllers/AuthenticatedSessionController';

export default function Login() {
    return (
        <>
            <Head title="Log in" />
            <Box sx={{ maxWidth: 420, mx: 'auto', p: 2, pt: 8 }}>
                <Paper sx={{ p: 3 }}>
                    <Typography variant="h5" component="h1" sx={{ mb: 2 }}>
                        Log in
                    </Typography>
                    <Form {...store.form()} resetOnError={['password']}>
                        {({ errors, processing }) => (
                            <Stack spacing={2}>
                                {errors.email && (
                                    <Alert severity="error">
                                        {errors.email}
                                    </Alert>
                                )}
                                <TextField
                                    name="email"
                                    type="email"
                                    label="Email"
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    error={Boolean(errors.email)}
                                />
                                <TextField
                                    name="password"
                                    type="password"
                                    label="Password"
                                    autoComplete="current-password"
                                    required
                                    error={Boolean(errors.password)}
                                    helperText={errors.password}
                                />
                                <FormControlLabel
                                    control={
                                        <Checkbox name="remember" value="1" />
                                    }
                                    label="Remember me"
                                />
                                <Button
                                    type="submit"
                                    variant="contained"
                                    disabled={processing}
                                >
                                    Log in
                                </Button>
                            </Stack>
                        )}
                    </Form>
                </Paper>
            </Box>
        </>
    );
}
