import { Link, router, usePage } from '@inertiajs/react';
import { AppBar, Box, Button, Toolbar, Typography } from '@mui/material';
import type { ReactNode } from 'react';
import {
    calendar,
    home,
    login,
    logout,
    planCalendarEdit,
    planCalendarView,
} from '@/routes';
import { index as accounts } from '@/routes/accounts';
import { index as emailNotifications } from '@/routes/email-notifications';
import { index as planCategories } from '@/routes/plan-categories';
import { index as planImport } from '@/routes/plan-import';
import type { Permission } from '@/types';

type NavigationItem = {
    label: string;
    href: string;
    permission: Permission | null;
};

const navigationItems: NavigationItem[] = [
    { label: 'Home', href: home().url, permission: null },
    { label: 'Calendar', href: calendar().url, permission: 'calendar.view' },
    {
        label: 'Plan calendar view',
        href: planCalendarView().url,
        permission: 'plan-calendar.view',
    },
    {
        label: 'Plan calendar edit',
        href: planCalendarEdit().url,
        permission: 'plan-calendar.edit',
    },
    {
        label: 'Plan categories',
        href: planCategories().url,
        permission: 'plan-categories.manage',
    },
    {
        label: 'Import plan',
        href: planImport().url,
        permission: 'plan-import.manage',
    },
    {
        label: 'Email notifications',
        href: emailNotifications().url,
        permission: 'email-notifications.manage',
    },
    {
        label: 'Accounts',
        href: accounts().url,
        permission: 'accounts.manage',
    },
];

function isActive(currentUrl: string, href: string): boolean {
    const currentPath = currentUrl.split('?')[0];

    if (href === home().url) {
        return currentPath === href;
    }

    return currentPath === href || currentPath.startsWith(`${href}/`);
}

export default function AppLayout({ children }: { children?: ReactNode }) {
    const { url, props } = usePage();
    const { name, auth } = props;
    const { user } = auth;

    const visibleItems = navigationItems.filter(
        (item) =>
            item.permission === null ||
            user?.permissions.includes(item.permission),
    );

    return (
        <>
            <AppBar
                position="static"
                color="default"
                elevation={0}
                sx={{ borderBottom: 1, borderColor: 'divider' }}
            >
                <Toolbar
                    component="nav"
                    aria-label="Main navigation"
                    sx={{ flexWrap: 'wrap', gap: 1, py: 1 }}
                >
                    <Typography
                        variant="h6"
                        component="span"
                        sx={{ mr: 2, fontWeight: 700 }}
                    >
                        {name}
                    </Typography>
                    <Box
                        sx={{
                            display: 'flex',
                            flexWrap: 'wrap',
                            flexGrow: 1,
                            gap: 0.5,
                        }}
                    >
                        {visibleItems.map((item) => {
                            const active = isActive(url, item.href);

                            return (
                                <Button
                                    key={item.href}
                                    LinkComponent={Link}
                                    href={item.href}
                                    variant={active ? 'contained' : 'outlined'}
                                    aria-current={active ? 'page' : undefined}
                                >
                                    {item.label}
                                </Button>
                            );
                        })}
                    </Box>
                    {user === null ? (
                        <Button
                            LinkComponent={Link}
                            href={login().url}
                            color="inherit"
                            variant={
                                isActive(url, login().url) ? 'outlined' : 'text'
                            }
                        >
                            Log in
                        </Button>
                    ) : (
                        <Box
                            sx={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 1,
                            }}
                        >
                            <Typography variant="button" component="span">
                                {user.name} {user.surname} (
                                {user.permission_group ?? 'no group'})
                            </Typography>
                            <Button
                                color="inherit"
                                onClick={() => router.post(logout().url)}
                            >
                                Log out
                            </Button>
                        </Box>
                    )}
                </Toolbar>
            </AppBar>
            {children}
        </>
    );
}
