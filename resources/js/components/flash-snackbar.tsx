import { router } from '@inertiajs/react';
import { Alert, Snackbar } from '@mui/material';
import { useEffect, useState } from 'react';

type Message = {
    severity: 'success' | 'warning';
    text: string;
};

/**
 * Shows the `success` or `warning` message flashed by the last response.
 */
export default function FlashSnackbar({
    autoHideDuration = 4000,
}: {
    autoHideDuration?: number;
}) {
    const [message, setMessage] = useState<Message | null>(null);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const { success, warning } = event.detail.flash;

                if (success) {
                    setMessage({ severity: 'success', text: success });
                } else if (warning) {
                    setMessage({ severity: 'warning', text: warning });
                }
            }),
        [],
    );

    return (
        <Snackbar
            open={message !== null}
            autoHideDuration={autoHideDuration}
            onClose={() => setMessage(null)}
        >
            <Alert
                severity={message?.severity ?? 'success'}
                variant="filled"
                onClose={() => setMessage(null)}
            >
                {message?.text}
            </Alert>
        </Snackbar>
    );
}
