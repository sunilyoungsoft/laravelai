import type { FormEvent, ReactNode } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import { LoadingButton } from '@/components/loading-button';
import { PageHeader } from '@/components/page-header';
import { PlatformLayout } from '@/components/platform-layout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * The Company profile fields CompanyService::create accepts (the Company
 * $fillable set). System fields (status, database_name, audit) are never
 * exposed on this form (E1). Values start blank; the server validates and
 * returns field-keyed errors that Inertia maps back onto these keys (E3).
 */
type CreateCompanyForm = {
    name: string;
    slug: string;
    contact_person: string;
    email: string;
    secondary_email: string;
    phone: string;
    gst_number: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    country_code: string;
    postal_code: string;
};

const INITIAL_FORM: CreateCompanyForm = {
    name: '',
    slug: '',
    contact_person: '',
    email: '',
    secondary_email: '',
    phone: '',
    gst_number: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    country_code: '',
    postal_code: '',
};

/**
 * Create Company page (Req E1–E4). A profile-only form wired with Inertia
 * useForm: it POSTs to /companies (platform.companies.store), which delegates
 * to CompanyService::create. On success the server redirects to the new
 * Company's Show page with a success flash (E4) — no client handling needed.
 * On validation failure the server returns field-keyed errors that render
 * inline beneath each input (E3/E4).
 */
export default function CompaniesCreate() {
    const { data, setData, post, processing, errors } =
        useForm<CreateCompanyForm>(INITIAL_FORM);

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        post('/companies');
    }

    return (
        <PlatformLayout>
            <Head title="New company" />

            <PageHeader
                title="New company"
                description="Create a company record. Workspace provisioning happens separately."
            />

            <form onSubmit={submit} className="mt-6 space-y-6" noValidate>
                <Card>
                    <CardHeader>
                        <CardTitle>Company profile</CardTitle>
                        <CardDescription>
                            Only profile details are captured here. The workspace
                            database is provisioned later from the company page.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="grid gap-6 sm:grid-cols-2">
                        <Field
                            id="name"
                            label="Name"
                            required
                            value={data.name}
                            error={errors.name}
                            onChange={(value) => setData('name', value)}
                            autoComplete="organization"
                        />

                        <Field
                            id="slug"
                            label="Slug"
                            required
                            value={data.slug}
                            error={errors.slug}
                            onChange={(value) => setData('slug', value)}
                            placeholder="acme-inc"
                            description="Lowercase letters, numbers, and hyphens."
                        />

                        <Field
                            id="contact_person"
                            label="Contact person"
                            value={data.contact_person}
                            error={errors.contact_person}
                            onChange={(value) => setData('contact_person', value)}
                            autoComplete="name"
                        />

                        <Field
                            id="email"
                            label="Email"
                            type="email"
                            value={data.email}
                            error={errors.email}
                            onChange={(value) => setData('email', value)}
                            autoComplete="email"
                        />

                        <Field
                            id="secondary_email"
                            label="Secondary email"
                            type="email"
                            value={data.secondary_email}
                            error={errors.secondary_email}
                            onChange={(value) => setData('secondary_email', value)}
                        />

                        <Field
                            id="phone"
                            label="Phone"
                            type="tel"
                            value={data.phone}
                            error={errors.phone}
                            onChange={(value) => setData('phone', value)}
                            autoComplete="tel"
                        />

                        <Field
                            id="gst_number"
                            label="GST number"
                            value={data.gst_number}
                            error={errors.gst_number}
                            onChange={(value) => setData('gst_number', value)}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Address</CardTitle>
                        <CardDescription>Optional contact address.</CardDescription>
                    </CardHeader>

                    <CardContent className="grid gap-6 sm:grid-cols-2">
                        <Field
                            id="address_line_1"
                            label="Address line 1"
                            className="sm:col-span-2"
                            value={data.address_line_1}
                            error={errors.address_line_1}
                            onChange={(value) => setData('address_line_1', value)}
                            autoComplete="address-line1"
                        />

                        <Field
                            id="address_line_2"
                            label="Address line 2"
                            className="sm:col-span-2"
                            value={data.address_line_2}
                            error={errors.address_line_2}
                            onChange={(value) => setData('address_line_2', value)}
                            autoComplete="address-line2"
                        />

                        <Field
                            id="city"
                            label="City"
                            value={data.city}
                            error={errors.city}
                            onChange={(value) => setData('city', value)}
                            autoComplete="address-level2"
                        />

                        <Field
                            id="state"
                            label="State"
                            value={data.state}
                            error={errors.state}
                            onChange={(value) => setData('state', value)}
                            autoComplete="address-level1"
                        />

                        <Field
                            id="country_code"
                            label="Country code"
                            value={data.country_code}
                            error={errors.country_code}
                            onChange={(value) => setData('country_code', value)}
                            placeholder="US"
                            maxLength={2}
                            description="Two-letter ISO country code."
                            autoComplete="country"
                        />

                        <Field
                            id="postal_code"
                            label="Postal code"
                            value={data.postal_code}
                            error={errors.postal_code}
                            onChange={(value) => setData('postal_code', value)}
                            autoComplete="postal-code"
                        />
                    </CardContent>
                </Card>

                <div className="flex items-center justify-end gap-3">
                    <Button variant="outline" asChild>
                        <Link href="/companies">Cancel</Link>
                    </Button>
                    <LoadingButton type="submit" loading={processing}>
                        Create company
                    </LoadingButton>
                </div>
            </form>
        </PlatformLayout>
    );
}

type FieldProps = {
    /** Field id; also the form key and the error key the server returns. */
    id: keyof CreateCompanyForm;
    label: string;
    value: string;
    /** Server-side validation message for this field, if any (E4). */
    error?: string;
    onChange: (value: string) => void;
    type?: string;
    required?: boolean;
    placeholder?: string;
    /** Optional hint rendered beneath the input when there is no error. */
    description?: string;
    maxLength?: number;
    autoComplete?: string;
    className?: string;
};

/**
 * A labeled text input with inline validation. When the server returns an
 * error for this field it is announced via aria-invalid + aria-describedby and
 * rendered beneath the input (E4); otherwise an optional description shows.
 */
function Field({
    id,
    label,
    value,
    error,
    onChange,
    type = 'text',
    required = false,
    placeholder,
    description,
    maxLength,
    autoComplete,
    className,
}: FieldProps): ReactNode {
    const errorId = `${id}-error`;
    const descriptionId = `${id}-description`;
    const describedBy = error ? errorId : description ? descriptionId : undefined;

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <Label htmlFor={id}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                )}
            </Label>
            <Input
                id={id}
                name={id}
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                placeholder={placeholder}
                maxLength={maxLength}
                autoComplete={autoComplete}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
            />
            {error ? (
                <p id={errorId} className="text-destructive text-sm">
                    {error}
                </p>
            ) : description ? (
                <p id={descriptionId} className="text-muted-foreground text-sm">
                    {description}
                </p>
            ) : null}
        </div>
    );
}
