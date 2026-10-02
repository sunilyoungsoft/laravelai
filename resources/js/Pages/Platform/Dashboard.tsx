import { Head, Link, usePage } from '@inertiajs/react';
import { Building2Icon, PlusIcon } from 'lucide-react';

import { CompanyStatusBadge } from '@/components/company-status-badge';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PlatformLayout } from '@/components/platform-layout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { statusMeta } from '@/lib/status';
import type { CompanyStatus, PageProps } from '@/types';

/**
 * Dashboard props. `statusCounts` always carries every CompanyStatus key
 * (DashboardController fills zeros for empty statuses) and `totalCompanies`
 * is their sum.
 */
type DashboardProps = PageProps<{
    statusCounts: Record<CompanyStatus, number>;
    totalCompanies: number;
}>;

/**
 * Stable card order driven by the shared `statusMeta` map. Keyed by every
 * CompanyStatus, so the six cards always render in the same order.
 */
const statusOrder = Object.keys(statusMeta) as CompanyStatus[];

/**
 * Platform admin dashboard (Req C1–C3): one status-count card per CompanyStatus
 * using the shared statusMeta for labels/colors, or a shared empty state when
 * there are no companies.
 */
export default function Dashboard() {
    const { statusCounts, totalCompanies, auth } = usePage<DashboardProps>().props;

    return (
        <PlatformLayout>
            <Head title="Dashboard" />

            <PageHeader title="Dashboard" description="Company overview by status." />

            <div className="mt-6">
                {totalCompanies === 0 ? (
                    <EmptyState
                        icon={Building2Icon}
                        title="No companies yet"
                        description="Create your first company to get started."
                        action={
                            auth.can['companies.create'] ? (
                                <Button asChild>
                                    <Link href="/companies/create">
                                        <PlusIcon aria-hidden="true" />
                                        Create company
                                    </Link>
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {statusOrder.map((status) => (
                            <StatusCountCard
                                key={status}
                                status={status}
                                count={statusCounts[status]}
                            />
                        ))}
                    </div>
                )}
            </div>
        </PlatformLayout>
    );
}

type StatusCountCardProps = {
    status: CompanyStatus;
    count: number;
};

/**
 * A single status card: the colored status badge for consistent labeling and
 * the count of companies currently in that status.
 */
function StatusCountCard({ status, count }: StatusCountCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>
                    <CompanyStatusBadge status={status} />
                </CardDescription>
                <CardTitle className="text-3xl tabular-nums">{count}</CardTitle>
            </CardHeader>
        </Card>
    );
}
