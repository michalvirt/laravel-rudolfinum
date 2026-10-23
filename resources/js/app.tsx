import '@fontsource/roboto/300.css';
import '@fontsource/roboto/400.css';
import '@fontsource/roboto/500.css';
import '@fontsource/roboto/700.css';

import { createInertiaApp } from '@inertiajs/react';
import CssBaseline from '@mui/material/CssBaseline';
import { createTheme, ThemeProvider } from '@mui/material/styles';
import AppLayout from '@/layouts/app-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const theme = createTheme({
    colorSchemes: {
        dark: true,
    },
});

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: () => AppLayout,
    progress: {
        color: '#4B5563',
    },
    withApp: (app) => (
        <ThemeProvider theme={theme}>
            <CssBaseline enableColorScheme />
            {app}
        </ThemeProvider>
    ),
});
