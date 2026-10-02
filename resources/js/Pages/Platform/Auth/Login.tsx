import { type FormEvent } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LoadingButton } from '@/components/loading-button';
import type { SharedProps } from '@/types';

type LoginForm = {
    email: string;
    password: string;
};

/**
 * Standalone Platform admin login page (no PlatformLayout — the visitor is not
 * authenticated yet). Posts email + password to POST /login. The backend returns
 * a single, non-disclosing credential error keyed to `email` (auth.failed); we
 * render only that error and never a password-specific message (Req A2, A3).
 */
export default function Login() {
    const { appName } = usePage<SharedProps>().props;

    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post('/login', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <div className="bg-background text-foreground flex min-h-screen items-center justify-center p-4">
            <Head title="Log in" />

            <Card className="w-full max-w-sm">
                <CardHeader className="text-center">
                    <CardTitle className="justify-self-center text-xl">{appName}</CardTitle>
                    <CardDescription>Sign in to the admin area</CardDescription>
                </CardHeader>

                <CardContent>
                    <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="username"
                                autoFocus
                                required
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                aria-invalid={errors.email ? true : undefined}
                                aria-describedby={errors.email ? 'login-error' : undefined}
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                autoComplete="current-password"
                                required
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                aria-invalid={errors.email ? true : undefined}
                                aria-describedby={errors.email ? 'login-error' : undefined}
                            />
                        </div>

                        {errors.email && (
                            <p
                                id="login-error"
                                role="alert"
                                className="text-destructive text-sm"
                            >
                                {errors.email}
                            </p>
                        )}

                        <LoadingButton type="submit" loading={processing} className="w-full">
                            Log in
                        </LoadingButton>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
