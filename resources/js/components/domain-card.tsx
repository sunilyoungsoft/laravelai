import { GlobeIcon } from 'lucide-react';

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/empty-state';
import type { Domain } from '@/types';

export type DomainCardProps = {
    /** The single effective (primary) domain, or null when none exists. */
    domain: Domain | null;
    /** Whether the acting user may manage domains (policy `manageDomains`). */
    can: boolean;
    /** Emitted when the user wants to add the first domain (none exists). */
    onAdd?: () => void;
    /** Emitted when the user wants to change the existing effective domain. */
    onChange?: () => void;
};

/**
 * Domain card (F4/F5): shows the single effective domain, or an empty state
 * with "Add Domain" when none exists. When a domain exists it offers
 * "Change Domain". Presentational only — it emits intent; the Show page owns
 * the actual form submission. Affordances are gated by `can` (UX only).
 */
export function DomainCard({ domain, can, onAdd, onChange }: DomainCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Domain</CardTitle>
            </CardHeader>
            <CardContent>
                {domain ? (
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="space-y-2">
                            <p className="text-sm font-medium">{domain.domain}</p>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">{domain.type}</Badge>
                                <Badge
                                    variant={
                                        domain.status === 'active'
                                            ? 'success'
                                            : domain.status === 'verification_failed'
                                              ? 'destructive'
                                              : 'secondary'
                                    }
                                >
                                    {domain.status}
                                </Badge>
                            </div>
                        </div>
                        {can && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onChange}
                            >
                                Change domain
                            </Button>
                        )}
                    </div>
                ) : (
                    <EmptyState
                        icon={GlobeIcon}
                        title="No domain yet"
                        description="This company has no effective domain."
                        action={
                            can ? (
                                <Button type="button" size="sm" onClick={onAdd}>
                                    Add domain
                                </Button>
                            ) : undefined
                        }
                    />
                )}
            </CardContent>
        </Card>
    );
}
