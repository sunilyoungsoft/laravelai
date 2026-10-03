import { type ReactNode, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { LogOutIcon } from 'lucide-react';

import { LoadingButton } from '@/components/loading-button';
import type { SharedProps } from '@/types';

export type WorkspaceLayoutProps = {
    children: ReactNode;
};

/**
 * Authenticated Workspace (tenant) shell. Deliberately standalone — it shares no
 * navigation with the Platform admin layout, since workspace users never see platform
 * features. Shows the app name, the current workspace user, and a logout control that
 * posts to the workspace logout on the current Company host.
 */
export function WorkspaceLayout({ children }: WorkspaceLayoutProps) {
    const { appName, workspaceAuth } = usePage<SharedProps>().props;
    const [loggingOut, setLoggingOut] = useState(false);

    function logout() {
        setLoggingOut(true);
        router.post('/logout', {}, { onFinish: () => setLoggingOut(false) });
    }

    return (
        <div className="bg-background text-foreground flex min-h-screen flex-col">
            <header className="flex h-14 items-center justify-between gap-4 border-b px-4 sm:px-6">
                <span className="font-semibold tracking-tight">{appName}</span>
                <div className="flex items-center gap-3">
                    {workspaceAuth.user && (
                        <span className="text-muted-foreground hidden text-sm sm:inline">
                            {workspaceAuth.user.name}
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

            <main className="flex-1 p-4 sm:p-6">{children}</main>
        </div>
    );
}
