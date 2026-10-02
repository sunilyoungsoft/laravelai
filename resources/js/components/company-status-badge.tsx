import { Badge } from '@/components/ui/badge';
import { statusMeta } from '@/lib/status';
import type { CompanyStatus } from '@/types';

export type CompanyStatusBadgeProps = {
    status: CompanyStatus;
};

/**
 * Renders a CompanyStatus as a labeled, colored badge using the shared
 * `statusMeta` map so status presentation is identical everywhere (Req 8).
 */
export function CompanyStatusBadge({ status }: CompanyStatusBadgeProps) {
    const { label, variant } = statusMeta[status];

    return <Badge variant={variant}>{label}</Badge>;
}
