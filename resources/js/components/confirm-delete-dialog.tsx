import { Form } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
    Stack,
} from '@mui/material';
import type { ComponentProps, ReactNode } from 'react';

/**
 * Asks for a confirmation and submits the delete form. With `errorField` the
 * validation error of that field is shown in the dialog.
 */
export default function ConfirmDeleteDialog({
    title,
    form,
    errorField,
    onClose,
    children,
}: {
    title: string;
    form: Pick<ComponentProps<typeof Form>, 'action' | 'method'>;
    errorField?: string;
    onClose: () => void;
    children: ReactNode;
}) {
    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="xs">
            <Form {...form} onSuccess={onClose}>
                {({ errors, processing }) => (
                    <>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogContent>
                            <Stack spacing={2}>
                                <DialogContentText>
                                    {children}
                                </DialogContentText>
                                {errorField && errors[errorField] && (
                                    <Alert severity="error">
                                        {errors[errorField]}
                                    </Alert>
                                )}
                            </Stack>
                        </DialogContent>
                        <DialogActions>
                            <Button onClick={onClose}>Cancel</Button>
                            <Button
                                type="submit"
                                color="error"
                                variant="contained"
                                disabled={processing}
                            >
                                Delete
                            </Button>
                        </DialogActions>
                    </>
                )}
            </Form>
        </Dialog>
    );
}
