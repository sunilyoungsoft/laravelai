import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { CompanyStatusBadge } from '@/components/company-status-badge';
import type { CompanyDetail } from '@/types';

export type CompanyInformationCardProps = {
    company: CompanyDetail;
};

type Field = {
    label: string;
    value: string | null;
};

/**
 * Company profile + metadata display (F1). Null/empty profile fields are hidden
 * so the card only shows what the company actually has.
 */
export function CompanyInformationCard({ company }: CompanyInformationCardProps) {
    const addressFields = [
        company.address_line_1,
        company.address_line_2,
        [company.city, company.state, company.postal_code].filter(Boolean).join(', ') ||
            null,
        company.country_code,
    ].filter((part): part is string => Boolean(part));

    const fields: Field[] = [
        { label: 'Contact person', value: company.contact_person },
        { label: 'Email', value: company.email },
        { label: 'Secondary email', value: company.secondary_email },
        { label: 'Phone', value: company.phone },
        { label: 'GST number', value: company.gst_number },
        { label: 'Created', value: company.created_at },
    ];

    const visibleFields = fields.filter((field) => Boolean(field.value));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Company information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <dl className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1">
                        <dt className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            Name
                        </dt>
                        <dd className="text-sm font-medium">{company.name}</dd>
                    </div>
                    <div className="space-y-1">
                        <dt className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            Slug
                        </dt>
                        <dd className="text-sm font-medium">{company.slug}</dd>
                    </div>
                    <div className="space-y-1">
                        <dt className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            Status
                        </dt>
                        <dd>
                            <CompanyStatusBadge status={company.status} />
                        </dd>
                    </div>
                    {visibleFields.map((field) => (
                        <div key={field.label} className="space-y-1">
                            <dt className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                                {field.label}
                            </dt>
                            <dd className="text-sm">{field.value}</dd>
                        </div>
                    ))}
                </dl>

                {addressFields.length > 0 && (
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            Address
                        </p>
                        <address className="text-sm not-italic">
                            {addressFields.map((line) => (
                                <span key={line} className="block">
                                    {line}
                                </span>
                            ))}
                        </address>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
