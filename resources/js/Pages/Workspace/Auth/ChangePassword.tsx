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

type ChangePasswordForm = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

/**
 * Forced first-login password change (1F-F). Shown to a workspace user flagged
 * must_change_password; every other workspace route redirects here until the change
 * succeeds. The user enters the temporary password plus a new one; on success the
 * backend clears the flag and stores the new Argon2id hash, then redirects home.
 */
export default function WorkspaceChangePassword() {
    const { appName } = usePage<SharedProps>().props;

    const { data, setData, post, processing, errors, reset } = useForm<ChangePasswordForm>({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post('/password/change', {
            onFinish: () => reset('current_password', 'password', 'password_confirmation'),
        });
    }

    return (
        <div className="bg-background text-foreground flex min-h-screen items-center justify-center p-4">
            <Head title="Set a new password" />

            <Card className="w-full max-w-sm">
                <CardHeader className="text-center">
                    <CardTitle className="justify-self-center text-xl">{appName}</CardTitle>
                    <CardDescription>
                        Set a new password to finish signing in
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="current_password">Temporary password</Label>
                            <Input
                                id="current_password"
                                type="password"
                                name="current_password"
                                autoComplete="current-password"
                                autoFocus
                                required
                                value={data.current_password}
                                onChange={(event) =>
                                    setData('current_password', event.target.value)
                                }
                                aria-invalid={errors.current_password ? true : undefined}
                            />
                            {errors.current_password && (
                                <p role="alert" className="text-destructive text-sm">
                                    {errors.current_password}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="password">New password</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                autoComplete="new-password"
                                required
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                aria-invalid={errors.password ? true : undefined}
                            />
                            {errors.password && (
                                <p role="alert" className="text-destructive text-sm">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm new password
                            </Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                                value={data.password_confirmation}
                                onChange={(event) =>
                                    setData('password_confirmation', event.target.value)
                                }
                            />
                        </div>

                        <LoadingButton type="submit" loading={processing} className="w-full">
                            Set password
                        </LoadingButton>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
