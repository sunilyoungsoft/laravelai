import { Head, usePage } from '@inertiajs/react';

import { WorkspaceLayout } from '@/components/workspace-layout';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { SharedProps } from '@/types';

/**
 * Minimal authenticated Workspace landing (1F-C). Confirms a workspace user has signed
 * in to their Company's workspace. Business modules will replace this with real content
 * in later phases; for now it only greets the user and proves the authenticated shell.
 */
export default function WorkspaceHome() {
    const { workspaceAuth } = usePage<SharedProps>().props;

    return (
        <WorkspaceLayout>
            <Head title="Workspace" />

            <Card className="max-w-xl">
                <CardHeader>
                    <CardTitle>Welcome{workspaceAuth.user ? `, ${workspaceAuth.user.name}` : ''}</CardTitle>
                    <CardDescription>
                        You are signed in to your workspace.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="text-muted-foreground text-sm">
                        Business features will appear here as modules are enabled for this
                        workspace.
                    </p>
                </CardContent>
            </Card>
        </WorkspaceLayout>
    );
}
