import type { ComponentType, ReactNode, SVGProps } from 'react';

import { cn } from '@/lib/utils';

export type EmptyStateProps = {
    /** Primary message describing why the area is empty. */
    title: string;
    /** Optional supporting detail. */
    description?: string;
    /** Optional lucide (or compatible) icon component rendered above the title. */
    icon?: ComponentType<SVGProps<SVGSVGElement>>;
    /** Optional call-to-action (e.g. a button or link). */
    action?: ReactNode;
    className?: string;
};

/**
 * Reusable empty placeholder for lists and sections that have no data (G1).
 * Centered icon + message + optional action.
 */
export function EmptyState({
    title,
    description,
    icon: Icon,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-10 text-center',
                className,
            )}
        >
            {Icon && (
                <div className="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-full">
                    <Icon className="size-5" aria-hidden="true" />
                </div>
            )}
            <div className="space-y-1">
                <p className="text-sm font-medium">{title}</p>
                {description && (
                    <p className="text-muted-foreground text-sm">{description}</p>
                )}
            </div>
            {action && <div className="mt-1">{action}</div>}
        </div>
    );
}
