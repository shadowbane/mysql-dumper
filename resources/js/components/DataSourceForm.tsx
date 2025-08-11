import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Plug2, AlertCircle, CheckCircle } from 'lucide-react';
import { toast } from 'sonner';
import { DataSource } from '@/types/datasource';

interface DataSourceFormProps {
    dataSource?: DataSource;
    onSubmit: (data: any) => void;
    isEditing?: boolean;
}

interface FormData {
    name: string;
    host: string;
    port: number;
    database: string;
    username: string;
    password: string;
    is_active: boolean;
    skipped_tables: string;
}

export default function DataSourceForm({ dataSource, onSubmit, isEditing = false }: DataSourceFormProps) {
    const [isTestingConnection, setIsTestingConnection] = useState(false);
    const [connectionStatus, setConnectionStatus] = useState<'idle' | 'success' | 'error'>('idle');
    
    const { data, setData, processing, errors, clearErrors } = useForm<FormData>({
        name: dataSource?.name || '',
        host: dataSource?.host || '',
        port: dataSource?.port || 3306,
        database: dataSource?.database || '',
        username: dataSource?.username || '',
        password: '',
        is_active: dataSource?.is_active ?? true,
        skipped_tables: typeof dataSource?.skipped_tables === 'string' 
            ? dataSource.skipped_tables 
            : Array.isArray(dataSource?.skipped_tables) 
            ? dataSource.skipped_tables.join(', ')
            : '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit(data);
    };

    const testConnection = async () => {
        // Clear previous connection status
        setConnectionStatus('idle');
        
        // Validate required fields for connection test
        const requiredFields = ['host', 'port', 'database', 'username', 'password'];
        const missingFields = requiredFields.filter(field => !data[field as keyof FormData]);
        
        if (missingFields.length > 0) {
            toast.error('Missing required fields', {
                description: `Please fill in: ${missingFields.join(', ')}`,
            });
            return;
        }

        setIsTestingConnection(true);

        try {
            const testData = {
                host: data.host,
                port: data.port,
                database: data.database,
                username: data.username,
                password: data.password,
            };

            const response = await fetch('/data-sources/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(testData),
            });

            const result = await response.json();

            if (result.success) {
                setConnectionStatus('success');
                toast.success('Connection successful', {
                    description: 'Successfully connected to the database.',
                });
            } else {
                setConnectionStatus('error');
                toast.error('Connection failed', {
                    description: result.message || 'Failed to connect to the database.',
                });
            }
        } catch (error) {
            setConnectionStatus('error');
            toast.error('Connection error', {
                description: 'An error occurred while testing the connection.',
            });
        } finally {
            setIsTestingConnection(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Basic Information</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="My Database Connection"
                                className={errors.name ? 'border-red-500' : ''}
                            />
                            {errors.name && (
                                <p className="text-sm text-red-500">{errors.name}</p>
                            )}
                        </div>

                        <div className="flex items-center space-x-2">
                            <Switch
                                id="is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) => setData('is_active', checked)}
                            />
                            <Label htmlFor="is_active">Active</Label>
                        </div>
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
                        <Alert className={connectionStatus === 'success' ? 'border-green-500' : 'border-red-500'}>
                            <div className="flex items-center">
                                {connectionStatus === 'success' ? (
                                    <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                                ) : (
                                    <AlertCircle className="h-4 w-4 text-red-500 mr-2" />
                                )}
                                <AlertDescription>
                                    {connectionStatus === 'success' 
                                        ? 'Connection successful! You can save this data source.' 
                                        : 'Connection failed. Please check your connection details.'}
                                </AlertDescription>
                            </div>
                        </Alert>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="host">Host</Label>
                            <Input
                                id="host"
                                type="text"
                                value={data.host}
                                onChange={(e) => {
                                    setData('host', e.target.value);
                                    setConnectionStatus('idle');
                                }}
                                placeholder="localhost or 127.0.0.1"
                                className={errors.host ? 'border-red-500' : ''}
                            />
                            {errors.host && (
                                <p className="text-sm text-red-500">{errors.host}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="port">Port</Label>
                            <Input
                                id="port"
                                type="number"
                                value={data.port}
                                onChange={(e) => {
                                    setData('port', parseInt(e.target.value) || 3306);
                                    setConnectionStatus('idle');
                                }}
                                placeholder="3306"
                                className={errors.port ? 'border-red-500' : ''}
                            />
                            {errors.port && (
                                <p className="text-sm text-red-500">{errors.port}</p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="database">Database Name</Label>
                        <Input
                            id="database"
                            type="text"
                            value={data.database}
                            onChange={(e) => {
                                setData('database', e.target.value);
                                setConnectionStatus('idle');
                            }}
                            placeholder="my_database"
                            className={errors.database ? 'border-red-500' : ''}
                        />
                        {errors.database && (
                            <p className="text-sm text-red-500">{errors.database}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="username">Username</Label>
                            <Input
                                id="username"
                                type="text"
                                value={data.username}
                                onChange={(e) => {
                                    setData('username', e.target.value);
                                    setConnectionStatus('idle');
                                }}
                                placeholder="database_user"
                                className={errors.username ? 'border-red-500' : ''}
                            />
                            {errors.username && (
                                <p className="text-sm text-red-500">{errors.username}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password">
                                Password {isEditing && <span className="text-sm text-muted-foreground">(leave blank to keep current)</span>}
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => {
                                    setData('password', e.target.value);
                                    setConnectionStatus('idle');
                                }}
                                placeholder={isEditing ? "Leave blank to keep current" : "database_password"}
                                className={errors.password ? 'border-red-500' : ''}
                            />
                            {errors.password && (
                                <p className="text-sm text-red-500">{errors.password}</p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Backup Options</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="skipped_tables">
                            Skipped Tables
                        </Label>
                        <Textarea
                            id="skipped_tables"
                            value={data.skipped_tables}
                            onChange={(e) => setData('skipped_tables', e.target.value)}
                            placeholder="cache, sessions, job_batches, activity_log"
                            className={`min-h-[100px] ${errors.skipped_tables ? 'border-red-500' : ''}`}
                        />
                        <p className="text-sm text-muted-foreground">
                            Enter table names separated by commas. These tables will be excluded from backups.
                        </p>
                        {errors.skipped_tables && (
                            <p className="text-sm text-red-500">{errors.skipped_tables}</p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="flex items-center gap-2">
                <Button 
                    type="submit" 
                    disabled={processing}
                    className="min-w-[120px]"
                >
                    {processing ? (
                        <>
                            <Loader2 className="h-4 w-4 animate-spin mr-2" />
                            Saving...
                        </>
                    ) : (
                        isEditing ? 'Update' : 'Create'
                    )}
                </Button>
                
                <Button 
                    type="button" 
                    variant="outline"
                    onClick={() => window.history.back()}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}