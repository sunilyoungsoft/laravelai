import { FormEvent, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

type FlashProps = {
    flash?: {
        success?: string;
        demo_note_id?: string;
    };
};

export default function Create() {
    const { flash } = usePage<FlashProps>().props;
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    function submit(event: FormEvent) {
        event.preventDefault();
        setProcessing(true);

        router.post(
            '/demo-notes',
            { title, body: body || null },
            {
                preserveScroll: true,
                onError: (pageErrors) => setErrors(pageErrors),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setTitle('');
                    setBody('');
                    setErrors({});
                },
            },
        );
    }

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <main className="mx-auto flex min-h-screen max-w-xl flex-col justify-center gap-6 px-6 py-16">
                <p className="text-sm font-medium uppercase tracking-wide text-slate-500">
                    Architecture proof
                </p>
                <h1 className="text-3xl font-semibold tracking-tight">Create demo note</h1>
                <p className="text-slate-600">
                    Temporary Demo module proving Web → Request → DTO → Action. Not CRM data.
                </p>

                {flash?.success ? (
                    <p className="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        {flash.success}
                        {flash.demo_note_id ? ` (${flash.demo_note_id})` : ''}
                    </p>
                ) : null}

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <label className="flex flex-col gap-1 text-sm">
                        <span className="font-medium text-slate-800">Title</span>
                        <input
                            className="rounded border border-slate-300 bg-white px-3 py-2"
                            value={title}
                            onChange={(event) => setTitle(event.target.value)}
                            required
                            maxLength={255}
                        />
                        {errors.title ? (
                            <span className="text-red-600">{errors.title}</span>
                        ) : null}
                    </label>

                    <label className="flex flex-col gap-1 text-sm">
                        <span className="font-medium text-slate-800">Body</span>
                        <textarea
                            className="min-h-28 rounded border border-slate-300 bg-white px-3 py-2"
                            value={body}
                            onChange={(event) => setBody(event.target.value)}
                            maxLength={5000}
                        />
                        {errors.body ? (
                            <span className="text-red-600">{errors.body}</span>
                        ) : null}
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                    >
                        {processing ? 'Saving…' : 'Create note'}
                    </button>
                </form>
            </main>
        </div>
    );
}
