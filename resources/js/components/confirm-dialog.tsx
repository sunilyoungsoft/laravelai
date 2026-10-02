import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { LoadingButton } from '@/components/loading-button';

export type ConfirmDialogProps = {
    /** Controlled open state. */
    open: boolean;
    /** Called when the dialog requests to open/close (overlay, escape, cancel). */
    onOpenChange: (open: boolean) => void;
    /** Dialog heading. */
    title: string;
    /** Optional explanatory body. */
    description?: string;
    /** Label for the confirm button. */
    confirmLabel?: string;
    /** Label for the cancel button. */
    cancelLabel?: string;
    /** Whether the confirm action is destructive (red confirm button). */
    destructive?: boolean;
    /** Invoked when the user confirms. */
    onConfirm: () => void;
    /** Pending state for the confirm action (spinner + disabled). */
    loading?: boolean;
};

/**
 * Confirmation modal for mutating or irreversible actions (G2). The confirm
 * button reflects a pending state; cancelling is disabled while loading.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    destructive = false,
    onConfirm,
    loading = false,
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={loading}
                    >
                        {cancelLabel}
                    </Button>
                    <LoadingButton
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={onConfirm}
                        loading={loading}
                    >
                        {confirmLabel}
                    </LoadingButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
