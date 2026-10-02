import { type ReactNode, useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Building2Icon, LayoutDashboardIcon, LogOutIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { LoadingButton } from '@/components/loading-button';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

type NavItem = {
    label: string;
    href: string;
    icon: typeof LayoutDashboardIcon;
    /** When set, the item is only shown if this ability is true. */
    requires?: boolean;
};

export type PlatformLayoutProps = {
    children: ReactNode;
};

/**
 * Authenticated Platform admin shell: sidebar navigation, a header with the
 * current user and logout, a flash region, and a main content slot. Reads the
 * shared `appName`, `auth`, and `flash` props so no product name is hardcoded.
 */
export function PlatformLayout({ children }: PlatformLayoutProps) {
    const { appName, auth, flash } = usePage<SharedProps>().props;
    const [loggingOut, setLoggingOut] = useState(false);

    const navItems: NavItem[] = [
        { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboardIcon },
        {
            label: 'Companies',
            href: '/companies',
            icon: Building2Icon,
            requires: auth.can['companies.viewAny'],
        },
    ];

    const visibleNav = navItems.filter((item) => item.requires !== false);

    function logout() {
        setLoggingOut(true);
        router.post(
            '/logout',
            {},
            { onFinish: () => setLoggingOut(false) },
        );
    }

    return (
        <div className="bg-background text-foreground flex min-h-screen">
            <aside className="hidden w-60 shrink-0 flex-col border-r md:flex">
                <div className="flex h-14 items-center border-b px-4">
                    <Link href="/dashboard" className="font-semibold tracking-tight">
                        {appName}
                    </Link>
                </div>
                <nav className="flex flex-1 flex-col gap-1 p-2">
                    {visibleNav.map((item) => (
                        <Button
                            key={item.href}
                            variant="ghost"
                            asChild
                            className="justify-start"
                        >
                            <Link href={item.href}>
                                <item.icon aria-hidden="true" />
                                {item.label}
                            </Link>
                        </Button>
                    ))}
                </nav>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-14 items-center justify-between gap-4 border-b px-4 sm:px-6">
                    <nav className="flex items-center gap-1 md:hidden">
                        {visibleNav.map((item) => (
                            <Button
                                key={item.href}
                                variant="ghost"
                                size="sm"
                                asChild
                            >
                                <Link href={item.href}>
                                    <item.icon aria-hidden="true" />
                                    <span className="sr-only">{item.label}</span>
                                </Link>
                            </Button>
                        ))}
                    </nav>
                    <div className="ml-auto flex items-center gap-3">
                        {auth.user && (
                            <span className="text-muted-foreground hidden text-sm sm:inline">
                                {auth.user.name}
                            </span>
                        )}
                        <LoadingButton
                            variant="outline"
                            size="sm"
                            onClick={logout}
                            loading={loggingOut}
                        >
                            <LogOutIcon aria-hidden="true" />
                            Log out
                        </LoadingButton>
                    </div>
                </header>

                <main className="flex-1 p-4 sm:p-6">
                    <FlashRegion success={flash.success} error={flash.error} />
                    {children}
                </main>
            </div>
        </div>
    );
}

type FlashRegionProps = {
    success: string | null;
    error: string | null;
};

/**
 * Renders the shared success/error flash messages. Auto-dismiss is intentionally
 * omitted; the message clears on the next Inertia visit.
 */
function FlashRegion({ success, error }: FlashRegionProps) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        setVisible(true);
    }, [success, error]);

    if (!visible || (!success && !error)) {
        return null;
    }

    return (
        <div className="mb-6 space-y-2">
            {success && (
                <div
                    role="status"
                    className={cn(
                        'rounded-md border px-3 py-2 text-sm',
                        'border-success/40 bg-success/10 text-success',
                    )}
                >
                    {success}
                </div>
            )}
            {error && (
                <div
                    role="alert"
                    className={cn(
                        'rounded-md border px-3 py-2 text-sm',
                        'border-destructive/40 bg-destructive/10 text-destructive',
                    )}
                >
                    {error}
                </div>
            )}
        </div>
    );
}
