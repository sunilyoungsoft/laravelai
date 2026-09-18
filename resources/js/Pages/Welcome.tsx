import { WelcomeProps } from '@/types';

export default function Welcome({ appName, laravelVersion, phpVersion }: WelcomeProps) {
    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <main className="mx-auto flex min-h-screen max-w-3xl flex-col justify-center gap-6 px-6 py-16">
                <p className="text-sm font-medium uppercase tracking-wide text-slate-500">
                    Phase 0 foundation
                </p>
                <h1 className="text-4xl font-semibold tracking-tight">{appName}</h1>
                <p className="max-w-xl text-lg text-slate-600">
                    Modular monolith ERP SaaS starter with Laravel, Inertia, React, and
                    TypeScript. Authentication, tenancy provisioning, and business modules
                    are intentionally not included yet.
                </p>
                <dl className="grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
                    <div>
                        <dt className="font-medium text-slate-800">Laravel</dt>
                        <dd>{laravelVersion}</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-slate-800">PHP</dt>
                        <dd>{phpVersion}</dd>
                    </div>
                </dl>
            </main>
        </div>
    );
}
