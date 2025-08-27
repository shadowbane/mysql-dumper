import React, {useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Loader2, Plug2, AlertCircle, CheckCircle, XCircle } from 'lucide-react';
import { toast } from 'sonner';
import { DataSource } from '@/types/datasource';
import {
    dataSourceSchema,
    dataSourceEditSchema,
    DataSourceFormData,
    DataSourceEditFormData
} from '@/schemas/dataSourceSchema';
import { route } from 'ziggy-js';
import axios from 'axios';

interface DataSourceFormProps {
    dataSource?: DataSource;
    onSubmit: (data: DataSourceFormData | DataSourceEditFormData) => void;
    isEditing?: boolean;
    errors?: {
        [key: string]: string | undefined;
    };
}

export default function DataSourceForm({ dataSource, onSubmit, isEditing = false, errors = [] }: DataSourceFormProps) {
    const [isTestingConnection, setIsTestingConnection] = useState(false);
    const [connectionStatus, setConnectionStatus] = useState<'idle' | 'success' | 'error'>('idle');
    const [generalErrors, setGeneralErrors] = useState<string[]>([]);

    const schema = isEditing ? dataSourceEditSchema : dataSourceSchema;
    const form = useForm<DataSourceFormData | DataSourceEditFormData>({
        resolver: zodResolver(schema),
        defaultValues: {
            name: dataSource?.name || '',
            host: dataSource?.host || '',
            port: dataSource?.port || 3306,
            database: dataSource?.database || '',
            username: dataSource?.username || '',
            password: '',
            is_active: dataSource?.is_active ?? true,
            skipped_tables: dataSource?.skipped_tables || '',
            structure_only: dataSource?.structure_only || '',
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
                } else if (form.data[key as keyof typeof form.data] !== undefined) {
                    // If the key exists in the form's values, it's a field error
                    if (value) fieldErrors[key] = value;
                } else {
                    if (value) formErrors.push(value);
                }
            }

            // Set field-specific errors
            Object.entries(fieldErrors).forEach(([key, value]) => {
                form.setError(key as keyof typeof form.data, value);
            });

            // Set general errors
            setGeneralErrors(formErrors);
        } else {
            // Clear errors when no errors exist
            setGeneralErrors([]);
        }
    }, [errors]);

    const handleSubmit = (values: DataSourceFormData | DataSourceEditFormData) => {
        onSubmit(values);
    };

    const testConnection = async () => {
        // Clear previous connection status
        setConnectionStatus('idle');

        // Validate required fields for connection test
        const requiredFields = ['host', 'port', 'database', 'username', 'password'];
        const formData = form.getValues();
        const missingFields = requiredFields.filter(field => !formData[field as keyof typeof formData]);

        if (missingFields.length > 0) {
            toast.error('Missing required fields', {
                description: `Please fill in: ${missingFields.join(', ')}`,
            });
            return;
        }

        setIsTestingConnection(true);

        try {
            const formData = form.getValues();
            const testData = {
                host: formData.host,
                port: formData.port,
                database: formData.database,
                username: formData.username,
                password: formData.password,
            };

            const response = await axios.post(route('data-sources.test-connection'), testData);

            if (response.data.success) {
                setConnectionStatus('success');
                toast.success('Connection successful', {
                    description: 'Successfully connected to the database.',
                });
            } else {
                setConnectionStatus('error');
                toast.error('Connection failed', {
                    description: response.data.message || 'Failed to connect to the database.',
                });
            }
        } catch (error: any) {
            setConnectionStatus('error');
            toast.error('Connection error', {
                description: error.response?.data?.message || 'An error occurred while testing the connection.',
            });
        } finally {
            setIsTestingConnection(false);
        }
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
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FormField
                                control={form.control}
                                name="name"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Name</FormLabel>
                                        <FormControl>
                                            <Input
                                                {...field}
                                                placeholder="My Database Connection"
                                            />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />

                            <FormField
                                control={form.control}
                                name="is_active"
                                render={({ field }) => (
                                    <FormItem className="flex flex-row items-center justify-between rounded-lg border p-3 shadow-sm">
                                        <div className="space-y-0.5">
                                            <FormLabel>Active</FormLabel>
                                            <FormDescription>
                                                Enable or disable this data source
                                            </FormDescription>
                                        </div>
                                        <FormControl>
                                            <Switch
                                                checked={field.value}
                                                onCheckedChange={field.onChange}
                                            />
                                        </FormControl>
                                    </FormItem>
                                )}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            Connection Details
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={testConnection}
                                disabled={isTestingConnection}
                                className="ml-2"
                            >
                                {isTestingConnection ? (
                                    <Loader2 className="h-4 w-4 animate-spin mr-1" />
                                ) : (
                                    <Plug2 className="h-4 w-4 mr-1" />
                                )}
                                Test Connection
                            </Button>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {connectionStatus !== 'idle' && (
                            <Alert
                                className={
                                    connectionStatus === 'success'
                                        ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                                        : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                }
                            >
                                {connectionStatus === 'success' ? (
                                    <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                                ) : (
                                    <AlertCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
                                )}
                                <AlertDescription className={connectionStatus === 'success' ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'}>
                                    {connectionStatus === 'success'
                                        ? 'Connection successful! You can save this data source.'
                                        : 'Connection failed. Please check your connection details.'}
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FormField
                                control={form.control}
                                name="host"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Host</FormLabel>
                                        <FormControl>
                                            <Input
                                                {...field}
                                                placeholder="localhost or 127.0.0.1"
                                                onChange={(e) => {
                                                    field.onChange(e.target.value);
                                                    setConnectionStatus('idle');
                                                }}
                                            />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />

                            <FormField
                                control={form.control}
                                name="port"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Port</FormLabel>
                                        <FormControl>
                                            <Input
                                                {...field}
                                                type="number"
                                                placeholder="3306"
                                                onChange={(e) => {
                                                    field.onChange(parseInt(e.target.value) || 3306);
                                                    setConnectionStatus('idle');
                                                }}
                                            />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                        </div>

                        <FormField
                            control={form.control}
                            name="database"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Database Name</FormLabel>
                                    <FormControl>
                                        <Input
                                            {...field}
                                            placeholder="my_database"
                                            onChange={(e) => {
                                                field.onChange(e.target.value);
                                                setConnectionStatus('idle');
                                            }}
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FormField
                                control={form.control}
                                name="username"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Username</FormLabel>
                                        <FormControl>
                                            <Input
                                                {...field}
                                                placeholder="database_user"
                                                onChange={(e) => {
                                                    field.onChange(e.target.value);
                                                    setConnectionStatus('idle');
                                                }}
                                            />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />

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
                                                placeholder={isEditing ? "Leave blank to keep current" : "database_password"}
                                                onChange={(e) => {
                                                    field.onChange(e.target.value);
                                                    setConnectionStatus('idle');
                                                }}
                                            />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Backup Options</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FormField
                            control={form.control}
                            name="skipped_tables"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Skipped Tables</FormLabel>
                                    <FormControl>
                                        <Textarea
                                            {...field}
                                            placeholder="cache, sessions, job_batches, activity_log"
                                            className="min-h-[100px]"
                                        />
                                    </FormControl>
                                    <FormDescription>
                                        Enter table names separated by commas. These tables will be excluded from backups.
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="structure_only"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Structure Only Tables</FormLabel>
                                    <FormControl>
                                        <Textarea
                                            {...field}
                                            placeholder="logs, temporary_data, cache_entries"
                                            className="min-h-[100px]"
                                        />
                                    </FormControl>
                                    <FormDescription>
                                        Enter table names separated by commas. For these tables, only the structure (DDL) will be backed up, data will be skipped.
                                    </FormDescription>
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
