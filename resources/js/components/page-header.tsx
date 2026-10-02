import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export type PageHeaderProps = {
    /** Page title. */
    title: string;
    /** Optional supporting description below the title. */
    description?: string;
    /** Optional right-aligned actions (buttons, links). */
    actions?: ReactNode;
    className?: string;
};

/**
 * Consistent page heading: title + optional description on the left, optional
 * actions on the right (Req 6).
 */
export function PageHeader({ title, description, actions, className }: PageHeaderProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                {description && (
                    <p className="text-muted-foreground text-sm">{description}</p>
                )}
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}
