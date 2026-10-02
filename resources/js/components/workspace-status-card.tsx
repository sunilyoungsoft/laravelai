import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { CompanyStatusBadge } from '@/components/company-status-badge';
import { LoadingButton } from '@/components/loading-button';
import type { CompanyDetail, CompanyStatus } from '@/types';

export type WorkspaceStatusCardProps = {
    company: CompanyDetail;
    /** Whether the acting user may provision this company (policy `provision`). */
    can: boolean;
    /** Invoked when the provision/retry action is triggered. */
    onProvision?: () => void;
    /** Pending state while a provision request is in flight. */
    provisioning?: boolean;
};

type WorkspaceAction = {
    /** The button label, or null when no trigger applies to this status. */
    label: string | null;
    /** Human-readable summary of the current lifecycle state. */
    summary: string;
};

/**
 * Maps a CompanyStatus to its workspace lifecycle presentation and the correct
 * primary action per F2. Statuses without a trigger return a null label.
 */
function actionForStatus(status: CompanyStatus): WorkspaceAction {
    switch (status) {
        case 'pending':
            return { label: 'Provision', summary: 'No workspace database yet.' };
        case 'provisioning_failed':
            return {
                label: 'Retry',
                summary: 'The last provisioning attempt failed.',
            };
        case 'provisioning':
            return { label: null, summary: 'Provisioning is in progress.' };
        case 'active':
            return { label: null, summary: 'The workspace database is provisioned.' };
        case 'suspended':
            return { label: null, summary: 'This company is suspended.' };
        case 'deactivated':
            return { label: null, summary: 'This company is deactivated.' };
    }
}

/**
 * Workspace card (F2/F3): shows the provisioning lifecycle state derived from
 * the company status + whether a workspace database exists, and offers the
 * correct Provision/Retry action gated by `can`, with full pending state.
 */
export function WorkspaceStatusCard({
    company,
    can,
    onProvision,
    provisioning = false,
}: WorkspaceStatusCardProps) {
    const { label, summary } = actionForStatus(company.status);
    const inProgress = company.status === 'provisioning';
    const showAction = can && label !== null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Workspace</CardTitle>
                <CardDescription>{summary}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <dl className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1">
                        <dt className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            Status
                        </dt>
                        <dd>
                            <CompanyStatusBadge status={company.status} />
                        </dd>
                    </div>
                    <div className="space-y-1">
                        <dt className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            Database
                        </dt>
                        <dd className="text-sm">
                            {company.has_workspace_database
                                ? 'Provisioned'
                                : 'Not provisioned'}
                        </dd>
                    </div>
                </dl>

                {showAction && (
                    <LoadingButton
                        type="button"
                        onClick={onProvision}
                        loading={provisioning || inProgress}
                    >
                        {label}
                    </LoadingButton>
                )}
            </CardContent>
        </Card>
    );
}
