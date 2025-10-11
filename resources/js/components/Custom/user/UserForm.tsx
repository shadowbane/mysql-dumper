import React, { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Loader2, XCircle } from 'lucide-react';
import { User, Role } from '@/types/user';
import { DataSource } from '@/types/datasource';
import {
    userSchema,
    userEditSchema,
    UserFormData,
    UserEditFormData
} from '@/schemas/userSchema';

interface UserFormProps {
    user?: User;
    roles: Role[];
    dataSources: DataSource[];
    userRoles?: number[];
    userDataSources?: string[];
    onSubmit: (data: UserFormData | UserEditFormData) => void;
    isEditing?: boolean;
    errors?: {
        [key: string]: string | undefined;
    };
}

export default function UserForm({
    user,
    roles,
    dataSources,
    userRoles = [],
    userDataSources = [],
    onSubmit,
    isEditing = false,
    errors = {}
}: UserFormProps) {
    const [generalErrors, setGeneralErrors] = useState<string[]>([]);
    const [selectedRoles, setSelectedRoles] = useState<number[]>(userRoles);

    const schema = isEditing ? userEditSchema : userSchema;
    const form = useForm<UserFormData | UserEditFormData>({
        resolver: zodResolver(schema),
        defaultValues: {
            name: user?.name || '',
            email: user?.email || '',
            password: '',
            password_confirmation: '',
            roles: userRoles,
            data_sources: userDataSources,
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

    const handleSubmit = (values: UserFormData | UserEditFormData) => {
        onSubmit(values);
    };

    // Check if administrator role is selected
    const isAdministratorSelected = () => {
        const adminRole = roles.find(role => role.slug === 'administrator');
        return adminRole && selectedRoles.includes(adminRole.id!);
    };

    return (
        <Form {...form}>
            {/* General Error Banner */}
            {generalErrors.length > 0 && (
                <div className="space-y-2 mb-4">
                    {generalErrors.map((error, index) => (
                        <Alert key={index} variant="destructive">
                            <XCircle className="h-4 w-4"/>
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
                                            autoComplete={'name'}
                                            placeholder="John Doe"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="email"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Email</FormLabel>
                                    <FormControl>
                                        <Input
                                            {...field}
                                            type="email"
                                            autoComplete={'email'}
                                            placeholder="john@example.com"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Password</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FormField
                            control={form.control}
                            name="password"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>
                                        Password {isEditing && <span className="text-sm text-muted-foreground">(leave blank to keep current)</span>}
                                    </FormLabel>
                                    <FormControl>
                                        <Input
                                            {...field}
                                            type="password"
                                            autoComplete={'new-password'}
                                            placeholder={isEditing ? "Leave blank to keep current" : "••••••••"}
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
                                    <FormLabel>Confirm Password</FormLabel>
                                    <FormControl>
                                        <Input
                                            {...field}
                                            type="password"
                                            autoComplete={'new-password'}
                                            placeholder="••••••••"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Roles</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FormField
                            control={form.control}
                            name="roles"
                            render={() => (
                                <FormItem>
                                    <div className="space-y-2">
                                        {roles.map((role) => (
                                            <FormField
                                                key={role.id}
                                                control={form.control}
                                                name="roles"
                                                render={({ field }) => {
                                                    return (
                                                        <FormItem
                                                            key={role.id}
                                                            className="flex flex-row items-start space-x-3 space-y-0"
                                                        >
                                                            <FormControl>
                                                                <Checkbox
                                                                    checked={field.value?.includes(role.id!)}
                                                                    onCheckedChange={(checked) => {
                                                                        const updatedRoles = checked
                                                                            ? [...field.value, role.id!]
                                                                            : field.value?.filter((value) => value !== role.id);
                                                                        field.onChange(updatedRoles);
                                                                        setSelectedRoles(updatedRoles);
                                                                    }}
                                                                />
                                                            </FormControl>
                                                            <div className="space-y-1 leading-none">
                                                                <FormLabel className="font-medium">
                                                                    {role.name}
                                                                </FormLabel>
                                                                {role.description && (
                                                                    <FormDescription>
                                                                        {role.description}
                                                                    </FormDescription>
                                                                )}
                                                            </div>
                                                        </FormItem>
                                                    );
                                                }}
                                            />
                                        ))}
                                    </div>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </CardContent>
                </Card>

                {!isAdministratorSelected() && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Source Access</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FormField
                                control={form.control}
                                name="data_sources"
                                render={() => (
                                    <FormItem>
                                        <FormDescription className="mb-4">
                                            Select which data sources this user can access. Administrators have access to all data sources by default.
                                        </FormDescription>
                                        <div className="space-y-2">
                                            {dataSources.map((dataSource) => (
                                                <FormField
                                                    key={dataSource.id}
                                                    control={form.control}
                                                    name="data_sources"
                                                    render={({ field }) => {
                                                        return (
                                                            <FormItem
                                                                key={dataSource.id}
                                                                className="flex flex-row items-start space-x-3 space-y-0"
                                                            >
                                                                <FormControl>
                                                                    <Checkbox
                                                                        checked={field.value?.includes(dataSource.id)}
                                                                        onCheckedChange={(checked) => {
                                                                            return checked
                                                                                ? field.onChange([...(field.value || []), dataSource.id])
                                                                                : field.onChange(
                                                                                    field.value?.filter(
                                                                                        (value) => value !== dataSource.id
                                                                                    )
                                                                                );
                                                                        }}
                                                                    />
                                                                </FormControl>
                                                                <div className="space-y-1 leading-none">
                                                                    <FormLabel className="font-medium">
                                                                        {dataSource.name}
                                                                    </FormLabel>
                                                                    <FormDescription>
                                                                        {dataSource.host}:{dataSource.port} ({dataSource.database})
                                                                    </FormDescription>
                                                                </div>
                                                            </FormItem>
                                                        );
                                                    }}
                                                />
                                            ))}
                                        </div>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                        </CardContent>
                    </Card>
                )}

                <div className="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => window.history.back()}
                    >
                        Cancel
                    </Button>

                    <Button
                        type="submit"
                        disabled={form.formState.isSubmitting}
                        className="min-w-[120px]"
                    >
                        {form.formState.isSubmitting ? (
                            <>
                                <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                Saving...
                            </>
                        ) : (
                            isEditing ? 'Update' : 'Create'
                        )}
                    </Button>
                </div>
            </form>
        </Form>
    );
}
