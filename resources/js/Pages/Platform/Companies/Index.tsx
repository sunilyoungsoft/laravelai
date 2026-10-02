import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PlusIcon, XIcon } from 'lucide-react';

import { CompanyTable } from '@/components/company-table';
import { PageHeader } from '@/components/page-header';
import { PlatformLayout } from '@/components/platform-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { statusMeta } from '@/lib/status';
import type { CompanyListItem, CompanyStatus, PageProps, Paginated } from '@/types';

/**
 * Companies list props (Req D1–D4). `companies` is the Laravel paginator as
 * serialized by CompanyController@index; `filters` echoes the active query so
 * the controls stay in sync with the (shareable) URL. `status` is the backing
 * CompanyStatus string value or null.
 */
type CompaniesIndexProps = PageProps<{
    companies: Paginated<CompanyListItem>;
    filters: {
        search: string | null;
        status: CompanyStatus | null;
    };
}>;

/** Stable status-option order driven by the shared statusMeta map. */
const statusOptions = Object.keys(statusMeta) as CompanyStatus[];

/** Debounce window (ms) before a search keystroke triggers a server visit. */
const SEARCH_DEBOUNCE_MS = 300;

/**
 * Build the filter payload for an Inertia visit, dropping empty values so the
 * URL only carries active filters (D2: shareable query string).
 */
function buildQuery(search: string, status: string): Record<string, string> {
    const query: Record<string, string> = {};

    if (search.trim() !== '') {
        query.search = search.trim();
    }

    if (status !== '') {
        query.status = status;
    }

    return query;
}

/**
 * Platform admin Companies list (Req D1–D4): the shared CompanyTable (rows,
 * pagination, and empty state), a free-text search input, and a status filter.
 * Search and status are driven by — and reflected back into — the URL query so
 * the view is shareable; the server re-filters on each visit. A subtle loading
 * affordance covers the in-flight visit (G1).
 */
export default function CompaniesIndex() {
    const { companies, filters, auth } = usePage<CompaniesIndexProps>().props;

    const [search, setSearch] = useState<string>(filters.search ?? '');
    const [status, setStatus] = useState<string>(filters.status ?? '');
    const [loading, setLoading] = useState<boolean>(false);

    // Skip the debounced visit on first mount and when the server rehydrates
    // our inputs from `filters` after a visit we initiated.
    const isFirstRender = useRef<boolean>(true);

    const canCreate = auth.can['companies.create'];
    const hasActiveFilters = search.trim() !== '' || status !== '';

    // Keep local controls in sync when the server echoes new filters (e.g. the
    // user navigated via a shared URL or pagination link).
    useEffect(() => {
        setSearch(filters.search ?? '');
        setStatus(filters.status ?? '');
    }, [filters.search, filters.status]);

    // Debounce the free-text search: after the user stops typing, navigate so
    // the server re-filters and the URL updates (D2).
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        if ((filters.search ?? '') === search.trim()) {
            return;
        }

        const timeout = window.setTimeout(() => {
            visit(buildQuery(search, status));
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeout);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    function visit(query: Record<string, string>): void {
        router.get('/companies', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    function onStatusChange(nextStatus: string): void {
        setStatus(nextStatus);
        visit(buildQuery(search, nextStatus));
    }

    function clearFilters(): void {
        setSearch('');
        setStatus('');
        router.get(
            '/companies',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    return (
        <PlatformLayout>
            <Head title="Companies" />

            <PageHeader
                title="Companies"
                description="Search and filter companies by status."
                actions={
                    canCreate ? (
                        <Button asChild>
                            <Link href="/companies/create">
                                <PlusIcon aria-hidden="true" />
                                New company
                            </Link>
                        </Button>
                    ) : undefined
                }
            />

            <div className="mt-6 space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="flex flex-1 flex-col gap-2">
                        <Label htmlFor="company-search">Search</Label>
                        <Input
                            id="company-search"
                            type="search"
                            placeholder="Search by name or slug"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <div className="flex flex-col gap-2 sm:w-56">
                        <Label htmlFor="company-status">Status</Label>
                        <select
                            id="company-status"
                            value={status}
                            onChange={(event) => onStatusChange(event.target.value)}
                            className={cn(
                                'border-input bg-background flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                            )}
                        >
                            <option value="">All statuses</option>
                            {statusOptions.map((value) => (
                                <option key={value} value={value}>
                                    {statusMeta[value].label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {hasActiveFilters && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={clearFilters}
                        >
                            <XIcon aria-hidden="true" />
                            Clear
                        </Button>
                    )}
                </div>

                <div
                    aria-busy={loading}
                    className={cn(
                        'transition-opacity',
                        loading && 'pointer-events-none opacity-60',
                    )}
                >
                    <CompanyTable
                        companies={companies}
                        emptyAction={
                            canCreate && !hasActiveFilters ? (
                                <Button asChild>
                                    <Link href="/companies/create">
                                        <PlusIcon aria-hidden="true" />
                                        New company
                                    </Link>
                                </Button>
                            ) : undefined
                        }
                    />
                </div>
            </div>
        </PlatformLayout>
    );
}
