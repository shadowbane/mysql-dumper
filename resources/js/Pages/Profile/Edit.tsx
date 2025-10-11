import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Loader2, XCircle } from 'lucide-react';
import MainLayout from '@/layouts/Main';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import { User } from '@/types/user';
import { profileSchema, ProfileFormData } from '@/schemas/profileSchema';

interface Props {
    user: User;
    errors?: { [key: string]: string };
}

export default function Edit({ user, errors }: Props) {
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [generalErrors, setGeneralErrors] = useState<string[]>([]);

    const form = useForm<ProfileFormData>({
        resolver: zodResolver(profileSchema),
        defaultValues: {
            name: user.name,
            password: '',
            password_confirmation: '',
        },
    });

    // Handle external errors (server-side validation)
    useEffect(() => {
        if (errors && Object.keys(errors).length > 0) {
            const formErrors: string[] = [];
            const fieldErrors: { [key: string]: string } = {};

            for (const [key, value] of Object.entries(errors)) {
                // Check if the key is a number (general error) or exists as a field in the form
                if (!isNaN(Number(key))) {
                    if (value) formErrors.push(value);
                } else if (form.getValues()[key as keyof typeof form.formState.defaultValues] !== undefined) {
                    // If the key exists in the form's values, it's a field error
                    if (value) fieldErrors[key] = value;
                } else {
                    if (value) formErrors.push(value);
                }
            }

            // Set field-specific errors
            Object.entries(fieldErrors).forEach(([key, value]) => {
                form.setError(key as any, { message: value });
            });

            // Set general errors
            setGeneralErrors(formErrors);
        } else {
            // Clear errors when no errors exist
            setGeneralErrors([]);
        }
    }, [errors]);

    const handleSubmit = (values: ProfileFormData) => {
        setIsSubmitting(true);
        router.put(route('profile.update'), values, {
            onSuccess: () => {
                toast.success('Profile updated');
                form.reset({
                    name: values.name,
                    password: '',
                    password_confirmation: '',
                });
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
            },
            onFinish: () => {
                setIsSubmitting(false);
            }
        });
    };

    return (
        <MainLayout>
            <Head title="Edit Profile" />

            <div className="flex flex-1 flex-col gap-4 p-4 pt-0 mt-4">
                <div className="mx-4">
                    <div className="max-w-2xl mx-auto">
                        <div className="mb-6">
                            <h1 className="text-2xl font-bold">Profile Settings</h1>
                            <p className="text-muted-foreground">
                                Update your personal information
                            </p>
                        </div>

                        <Form {...form}>
                            {/* General Error Banner */}
                            {generalErrors.length > 0 && (
                                <div className="space-y-2 mb-4">
                                    {generalErrors.map((error, index) => (
                                        <Alert key={index} variant="destructive">
                                            <XCircle className="h-4 w-4" />
                                            <AlertDescription>
                                                {error}
                                            </AlertDescription>
                                        </Alert>
                                    ))}
                                </div>
                            )}

                            <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Basic Information</CardTitle>
                                        <CardDescription>
                                            Update your name and email address
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <FormField
                                            control={form.control}
                                            name="name"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Name</FormLabel>
                                                    <FormControl>
                                                        <Input
                                                            {...field}
                                                            autoComplete="name"
                                                            placeholder="John Doe"
                                                        />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />

                                        <div>
                                            <FormLabel>Email</FormLabel>
                                            <Input
                                                value={user.email}
                                                disabled
                                                className="bg-muted"
                                                autoComplete={'email'}
                                            />
                                            <p className="text-sm text-muted-foreground mt-2">
                                                Email address cannot be changed
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Change Password</CardTitle>
                                        <CardDescription>
                                            Leave blank to keep your current password
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <FormField
                                            control={form.control}
                                            name="password"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>
                                                        New Password
                                                    </FormLabel>
                                                    <FormControl>
                                                        <Input
                                                            {...field}
                                                            type="password"
                                                            autoComplete="new-password"
                                                            placeholder="Leave blank to keep current"
                                                        />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />

                                        <FormField
                                            control={form.control}
                                            name="password_confirmation"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Confirm New Password</FormLabel>
                                                    <FormControl>
                                                        <Input
                                                            {...field}
                                                            type="password"
                                                            autoComplete="new-password"
                                                            placeholder="Confirm your new password"
                                                        />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    </CardContent>
                                </Card>

                                <div className="flex items-center justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => router.visit(route('dashboard'))}
                                    >
                                        Cancel
                                    </Button>

                                    <Button
                                        type="submit"
                                        disabled={isSubmitting}
                                        className="min-w-[120px]"
                                    >
                                        {isSubmitting ? (
                                            <>
                                                <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                                Saving...
                                            </>
                                        ) : (
                                            'Save Changes'
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </Form>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
