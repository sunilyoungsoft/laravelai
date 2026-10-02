import { Loader2Icon } from 'lucide-react';

import { Button, type ButtonProps } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type LoadingButtonProps = ButtonProps & {
    /** When true, shows a spinner and disables the button. */
    loading?: boolean;
};

/**
 * A Button that reflects a pending state: it disables itself and renders a
 * spinner while `loading` is true. Used for every mutating action so pending
 * UX stays consistent (G2).
 */
export function LoadingButton({
    loading = false,
    disabled,
    children,
    className,
    ...props
}: LoadingButtonProps) {
    return (
        <Button
            disabled={loading || disabled}
            aria-busy={loading}
            className={cn(className)}
            {...props}
        >
            {loading && <Loader2Icon className="animate-spin" aria-hidden="true" />}
            {children}
        </Button>
    );
}
