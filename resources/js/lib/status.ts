import type { CompanyStatus } from '@/types';

/**
 * shadcn/ui Badge variant tokens. These match the variants the generated
 * `components/ui/badge` primitive exposes; `success`/`warning` are project
 * additions layered on top of the shadcn defaults.
 */
export type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline' | 'success' | 'warning';

/** Display metadata for a single CompanyStatus. */
export type StatusMeta = {
    /** Human-readable label for badges and summaries. */
    label: string;
    /** shadcn Badge variant used by CompanyStatusBadge (task 4.2). */
    variant: BadgeVariant;
};

/**
 * Single source of truth for CompanyStatus labels and badge colors. Consumed by
 * CompanyStatusBadge and the Dashboard so status presentation stays consistent
 * across the UI. Keyed by every CompanyStatus so lookups are total (no fallback
 * needed).
 */
export const statusMeta: Record<CompanyStatus, StatusMeta> = {
    pending: { label: 'Pending', variant: 'secondary' },
    provisioning: { label: 'Provisioning', variant: 'warning' },
    active: { label: 'Active', variant: 'success' },
    provisioning_failed: { label: 'Provisioning failed', variant: 'destructive' },
    suspended: { label: 'Suspended', variant: 'warning' },
    deactivated: { label: 'Deactivated', variant: 'outline' },
};
