import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';

import { CompanyInformationCard } from '@/components/company-information-card';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DomainCard } from '@/components/domain-card';
import { LoadingButton } from '@/components/loading-button';
import { PageHeader } from '@/components/page-header';
import { PlatformLayout } from '@/components/platform-layout';
import { WorkspaceStatusCard } from '@/components/workspace-status-card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { statusMeta } from '@/lib/status';
import type {
    CompanyAbilities,
    CompanyDetail,
    Domain,
    DomainType,
    PageProps,
} from '@/types';

/**
 * Company Details props (Req F1–F5). `company` is the safe detail shape from
 * CompanyController@show (toDetail); `effectiveDomain` is the single primary
 * Domain or null; `can` is the per-company ability map resolved against this
 * Company for the acting user. The `can` map gates which actions render — the
 * server still authorizes every request (B2/B3).
 */
type CompanyShowProps = PageProps<{
    company: CompanyDetail;
    effectiveDomain: Domain | null;
    can: CompanyAbilities;
}>;

/** The add/change domain form payload posted to the domain endpoints (F5). */
type DomainForm = {
    domain: string;
    type: DomainType;
};

/** Stable domain-type options for the native select. */
const domainTypeOptions: ReadonlyArray<{ value: DomainType; label: string }> = [
    { value: 'subdomain', label: 'Subdomain' },
    { value: 'custom', label: 'Custom' },
];

/**
 * Company Details page (Req F1–F5, G1, G2). Composes the three shared cards —
 * Company information, Workspace, and Domain — and owns the two mutating flows:
 *
 *  - Provision/Retry: a significant operation, so it is confirmed via the shared
 *    ConfirmDialog before POSTing to the synchronous provision endpoint. The
 *    in-flight state drives the Workspace card's pending button; success/error
 *    arrive as a server flash rendered by PlatformLayout (F2/F3, G2).
 *  - Add/Change domain: a dialog form wired with Inertia useForm so field errors
 *    (errors.domain) surface inline. Add POSTs when no effective domain exists;
 *    Change PUTs to replace the effective one. On success the server redirects
 *    back here with a flash and the dialog closes (F4/F5, G1/G2).
 *
 * Per-company gating (F1/B3): the Workspace action only appears when can.provision
 * and the domain affordances only when can.manageDomains — UX only; the server
 * authorizes regardless.
 */
export default function CompaniesShow() {
    const { company, effectiveDomain, can } = usePage<CompanyShowProps>().props;

    const [confirmProvision, setConfirmProvision] = useState<boolean>(false);
    const [provisioning, setProvisioning] = useState<boolean>(false);
    const [domainDialogOpen, setDomainDialogOpen] = useState<boolean>(false);

    const isChange = effectiveDomain !== null;

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm<DomainForm>({
        domain: effectiveDomain?.domain ?? '',
        type: effectiveDomain?.type ?? 'subdomain',
    });

    // Keep the dialog form in sync with the server-provided effective domain
    // whenever it changes (e.g. after a successful change redirects back).
    useEffect(() => {
        setData({
            domain: effectiveDomain?.domain ?? '',
            type: effectiveDomain?.type ?? 'subdomain',
        });
        // Intentionally keyed on the server value only.
    }, [effectiveDomain?.domain, effectiveDomain?.type]); // eslint-disable-line react-hooks/exhaustive-deps

    /**
     * POST the synchronous provision/retry request (no body). The in-flight
     * state drives the Workspace card's pending button; the server sets a
     * success/error flash that PlatformLayout renders (F3). Confirm first (G2).
     */
    function provision(): void {
        router.post(
            `/companies/${company.id}/provision`,
            {},
            {
                preserveScroll: true,
                onStart: () => setProvisioning(true),
                onFinish: () => {
                    setProvisioning(false);
                    setConfirmProvision(false);
                },
            },
        );
    }

    function openDomainDialog(): void {
        clearErrors();
        setData({
            domain: effectiveDomain?.domain ?? '',
            type: effectiveDomain?.type ?? 'subdomain',
        });
        setDomainDialogOpen(true);
    }

    function closeDomainDialog(open: boolean): void {
        setDomainDialogOpen(open);

        if (!open) {
            clearErrors();
            reset();
        }
    }

    /**
     * Submit the add/change domain form. Add POSTs to the domain store route;
     * Change PUTs to the update route. On success the server redirects back to
     * this page with a flash — we close the dialog; validation errors stay on
     * the form via Inertia's errors bag (F5).
     */
    function submitDomain(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setDomainDialogOpen(false),
        } as const;

        const url = `/companies/${company.id}/domain`;

        if (isChange) {
            put(url, options);

            return;
        }

        post(url, options);
    }

    const statusLabel = statusMeta[company.status].label;

    return (
        <PlatformLayout>
            <Head title={company.name} />

            <PageHeader
                title={company.name}
                description={`${company.slug} · ${statusLabel}`}
                actions={
                    <Button variant="outline" asChild>
                        <Link href="/companies">
                            <ArrowLeftIcon aria-hidden="true" />
                            Back to companies
                        </Link>
                    </Button>
                }
            />

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div className="lg:col-span-2">
                    <CompanyInformationCard company={company} />
                </div>

                <WorkspaceStatusCard
                    company={company}
                    can={can.provision}
                    provisioning={provisioning}
                    onProvision={() => setConfirmProvision(true)}
                />

                <DomainCard
                    domain={effectiveDomain}
                    can={can.manageDomains}
                    onAdd={openDomainDialog}
                    onChange={openDomainDialog}
                />
            </div>

            <ConfirmDialog
                open={confirmProvision}
                onOpenChange={(open) => {
                    if (!provisioning) {
                        setConfirmProvision(open);
                    }
                }}
                title={
                    company.status === 'provisioning_failed'
                        ? 'Retry provisioning?'
                        : 'Provision workspace?'
                }
                description="This builds the workspace database for this company. It runs now and may take a moment."
                confirmLabel={
                    company.status === 'provisioning_failed' ? 'Retry' : 'Provision'
                }
                onConfirm={provision}
                loading={provisioning}
            />

            <Dialog open={domainDialogOpen} onOpenChange={closeDomainDialog}>
                <DialogContent>
                    <form onSubmit={submitDomain} noValidate>
                        <DialogHeader>
                            <DialogTitle>
                                {isChange ? 'Change domain' : 'Add domain'}
                            </DialogTitle>
                            <DialogDescription>
                                {isChange
                                    ? 'Replace the effective domain for this company.'
                                    : 'Set the effective domain for this company.'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="domain">Domain</Label>
                                <Input
                                    id="domain"
                                    name="domain"
                                    value={data.domain}
                                    onChange={(event) =>
                                        setData('domain', event.target.value)
                                    }
                                    placeholder="acme.example.com"
                                    autoComplete="off"
                                    aria-invalid={errors.domain ? true : undefined}
                                    aria-describedby={
                                        errors.domain ? 'domain-error' : undefined
                                    }
                                />
                                {errors.domain && (
                                    <p
                                        id="domain-error"
                                        className="text-destructive text-sm"
                                    >
                                        {errors.domain}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="type">Type</Label>
                                <select
                                    id="type"
                                    name="type"
                                    value={data.type}
                                    onChange={(event) =>
                                        setData(
                                            'type',
                                            event.target.value as DomainType,
                                        )
                                    }
                                    className={cn(
                                        'border-input bg-background flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                                        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                        'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                                    )}
                                    aria-invalid={errors.type ? true : undefined}
                                    aria-describedby={
                                        errors.type ? 'type-error' : undefined
                                    }
                                >
                                    {domainTypeOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.type && (
                                    <p
                                        id="type-error"
                                        className="text-destructive text-sm"
                                    >
                                        {errors.type}
                                    </p>
                                )}
                            </div>
                        </div>

                        <DialogFooter className="mt-6">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeDomainDialog(false)}
                                disabled={processing}
                            >
                                Cancel
                            </Button>
                            <LoadingButton type="submit" loading={processing}>
                                {isChange ? 'Change domain' : 'Add domain'}
                            </LoadingButton>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </PlatformLayout>
    );
}
