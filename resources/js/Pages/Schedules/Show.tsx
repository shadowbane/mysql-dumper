import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, Clock, Database, Activity, Edit, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import MainLayout from '@/layouts/Main';
import { Schedule } from '@/types/schedule';
import { route } from 'ziggy-js';

interface Props {
    schedule: Schedule;
}

export default function ShowSchedule({ schedule }: Props) {
    const formatDateTime = (dateString: string | null) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleString();
    };

    const getStatusVariant = (isActive: boolean) => {
        return isActive ? 'default' : 'secondary';
    };

    const getDaysDisplay = () => {
        const dayNames = {
            1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 
            5: 'Fri', 6: 'Sat', 7: 'Sun'
        };
        
        return schedule.days_of_week
            .sort((a, b) => a - b)
            .map(day => dayNames[day as keyof typeof dayNames])
            .join(', ');
    };

    return (
        <MainLayout>
            <Head title={`Schedule - ${schedule.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 pt-0 mt-4">
                <div className="mx-4">
                    <div className="mb-4">
                        <Link href={route('schedules.index')}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Schedules
                            </Button>
                        </Link>
                    </div>
                    
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold">Schedule Details</h1>
                            <p className="text-muted-foreground">
                                {schedule.name}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link href={route('schedules.edit', { schedule: schedule.id })}>
                                <Button variant="outline">
                                    <Edit className="h-4 w-4 mr-2" />
                                    Edit
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Schedule Information */}
                        <div className="lg:col-span-2 space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Calendar className="h-5 w-5" />
                                        Schedule Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Name</p>
                                            <p className="text-sm font-semibold">{schedule.name}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Status</p>
                                            <Badge variant={getStatusVariant(schedule.is_active)}>
                                                {schedule.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Time (UTC)</p>
                                            <p className="text-sm">{schedule.human_time}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Days</p>
                                            <p className="text-sm">{getDaysDisplay()}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Last Run</p>
                                            <p className="text-sm">{formatDateTime(schedule.last_run_at)}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Created</p>
                                            <p className="text-sm">{formatDateTime(schedule.created_at)}</p>
                                        </div>
                                    </div>

                                    {schedule.description && (
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Description</p>
                                            <p className="text-sm whitespace-pre-wrap">{schedule.description}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Data Sources */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Database className="h-5 w-5" />
                                        Data Sources ({schedule.data_sources?.length || 0})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {schedule.data_sources && schedule.data_sources.length > 0 ? (
                                        <div className="space-y-3">
                                            {schedule.data_sources.map((dataSource) => (
                                                <div key={dataSource.id} className="flex items-center justify-between p-3 border rounded-lg">
                                                    <div className="space-y-1">
                                                        <p className="font-medium">{dataSource.name}</p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {dataSource.host}:{dataSource.port} / {dataSource.database}
                                                        </p>
                                                    </div>
                                                    <Badge variant={dataSource.is_active ? 'default' : 'secondary'}>
                                                        {dataSource.is_active ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">No data sources assigned to this schedule.</p>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Recent Backups */}
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Activity className="h-5 w-5" />
                                        Recent Backups
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {schedule.backup_logs && schedule.backup_logs.length > 0 ? (
                                        <div className="space-y-3">
                                            {schedule.backup_logs.slice(0, 5).map((backup) => (
                                                <div key={backup.id} className="flex items-center justify-between p-3 border rounded-lg">
                                                    <div className="space-y-1">
                                                        <p className="text-sm font-medium">
                                                            {backup.data_source?.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {formatDateTime(backup.created_at)}
                                                        </p>
                                                    </div>
                                                    <Badge 
                                                        variant={
                                                            backup.status === 'Completed' ? 'default' :
                                                            backup.status === 'Failed' ? 'destructive' :
                                                            backup.status === 'Running' ? 'outline' : 'secondary'
                                                        }
                                                    >
                                                        {backup.status}
                                                    </Badge>
                                                </div>
                                            ))}
                                            
                                            {schedule.backup_logs.length > 5 && (
                                                <div className="text-center pt-2">
                                                    <Link 
                                                        href={route('backup-logs.index', { 
                                                            schedule_id: schedule.id 
                                                        })}
                                                        className="text-sm text-primary hover:underline"
                                                    >
                                                        View all {schedule.backup_logs.length} backups
                                                    </Link>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground text-sm">
                                            No backups have been created by this schedule yet.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Schedule Summary */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Clock className="h-5 w-5" />
                                        Schedule Summary
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="text-sm">
                                        <span className="font-medium">Pattern:</span>
                                        <br />
                                        {schedule.human_days} at {schedule.human_time}
                                    </div>
                                    
                                    <div className="text-sm">
                                        <span className="font-medium">Data Sources:</span>
                                        <br />
                                        {schedule.data_sources?.length || 0} database{(schedule.data_sources?.length || 0) !== 1 ? 's' : ''}
                                    </div>

                                    <div className="text-sm">
                                        <span className="font-medium">Status:</span>
                                        <br />
                                        <Badge variant={getStatusVariant(schedule.is_active)} className="mt-1">
                                            {schedule.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}