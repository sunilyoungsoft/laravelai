import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { Building2Icon } from 'lucide-react';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { CompanyStatusBadge } from '@/components/company-status-badge';
import { EmptyState } from '@/components/empty-state';
import { cn } from '@/lib/utils';
import type { CompanyListItem, Paginated, PaginationLink } from '@/types';

export type CompanyTableProps = {
    /** Paginated companies as serialized by CompanyController@index. */
    companies: Paginated<CompanyListItem>;
    /** Optional empty-state action (e.g. a "New company" button). */
    emptyAction?: ReactNode;
};

/**
 * Companies list: a table of rows (name, slug, status, effective domain,
 * created) linking to each company's Details page, plus pagination. Falls back
 * to a shared empty state when there are no rows (G1).
 */
export function CompanyTable({ companies, emptyAction }: CompanyTableProps) {
    if (companies.data.length === 0) {
        return (
            <EmptyState
                icon={Building2Icon}
                title="No companies found"
                description="No companies match the current filters yet."
                action={emptyAction}
            />
        );
    }

    return (
        <div className="space-y-4">
            <div className="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Domain</TableHead>
                            <TableHead>Created</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {companies.data.map((company) => (
                            <TableRow key={company.id} className="cursor-pointer">
                                <TableCell className="font-medium">
                                    <Link
                                        href={`/companies/${company.id}`}
                                        className="hover:underline"
                                    >
                                        {company.name}
                                    </Link>
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {company.slug}
                                </TableCell>
                                <TableCell>
                                    <CompanyStatusBadge status={company.status} />
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {company.effective_domain ?? '—'}
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {company.created_at ?? '—'}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination links={companies.links} total={companies.total} />
        </div>
    );
}

type PaginationProps = {
    links: PaginationLink[];
    total: number;
};

/**
 * Renders Laravel's paginator links. Non-URL links (ellipsis, disabled
 * prev/next) render as inert placeholders; active and navigable links are
 * Inertia visits that preserve the current query string via the server URL.
 */
function Pagination({ links, total }: PaginationProps) {
    // Nothing to page through: only the single disabled prev/next pair exists.
    if (links.length <= 3) {
        return (
            <p className="text-muted-foreground text-sm">
                {total} {total === 1 ? 'company' : 'companies'}
            </p>
        );
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-muted-foreground text-sm">
                {total} {total === 1 ? 'company' : 'companies'}
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {links.map((link, index) =>
                    link.url ? (
                        <Button
                            key={`${link.label}-${index}`}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            asChild
                        >
                            <Link
                                href={link.url}
                                preserveScroll
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        </Button>
                    ) : (
                        <span
                            key={`${link.label}-${index}`}
                            className={cn(
                                'text-muted-foreground px-2 py-1 text-sm',
                            )}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </div>
        </div>
    );
}
