import {Head, Link, router} from '@inertiajs/react';
import {BackupLog} from '@/types/backup-log';
import {ArrowLeft, Download, Trash2, Database, Clock, HardDrive, AlertTriangle, CheckCircle} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Card, CardContent, CardHeader, CardTitle} from '@/components/ui/card';
import {Badge} from '@/components/ui/badge';
import {toast} from 'sonner';
import MainLayout from '@/layouts/Main';
import {route} from 'ziggy-js';

interface Props {
    backupLog: BackupLog;
}

export default function BackupLogShow({backupLog}: Props) {
    const downloadBackup = async () => {
        try {
            window.open(route('backup-logs.download', {backup_log: backupLog.id}), '_blank');
        } catch (error: any) {
            toast.error("Download error", {
                description: "An error occurred while downloading the backup file.",
            });
        }
    };

    const deleteBackupFile = async () => {
        if (!confirm('Are you sure you want to delete this backup file? The log entry will be preserved but the file will be permanently deleted.')) {
            return;
        }

        router.delete(route('backup-logs.delete-file', {backup_log: backupLog.id}), {
            preserveScroll: true,
            onError: (errors: any) => {
                const errorMessage = errors?.message || errors?.error || errors?.[0] || 'An error occurred while deleting the backup file.';
                toast.error("Delete error", {
                    description: errorMessage,
                });
            },
            onSuccess: () => {
                toast.success("File deleted", {
                    description: "Backup file deleted successfully. Log entry preserved.",
                });
                // Navigate back to backup logs index
                router.visit(route('backup-logs.index'));
            }
        });
    };

    const getStatusVariant = (status: string) => {
        switch (status) {
            case 'Completed':
                return 'default';
            case 'Running':
                return 'outline';
            case 'Failed':
                return 'destructive';
            case 'Pending':
                return 'secondary';
            default:
                return 'secondary';
        }
    };

    const getTimelineIcon = (status: string) => {
        switch (status) {
            case 'Completed':
                return <CheckCircle className="h-4 w-4 text-green-600" />;
            case 'Running':
                return <Clock className="h-4 w-4 text-blue-600" />;
            case 'Failed':
                return <AlertTriangle className="h-4 w-4 text-red-600" />;
            case 'Pending':
                return <Clock className="h-4 w-4 text-gray-600" />;
            default:
                return <Clock className="h-4 w-4 text-gray-600" />;
        }
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleString();
    };

    const formatShortDate = (dateString: string | null) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleTimeString();
    };

    return (
        <MainLayout>
            <Head title={`Backup Log - ${backupLog.data_source?.name}`}/>

            <div className="flex flex-1 flex-col gap-4 p-4 pt-0 mt-4">
                <div className="mx-4">
                    {/* Header */}
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-4">
                            <Link href={route('backup-logs.index')}>
                                <Button variant="outline" size="sm">
                                    <ArrowLeft className="h-4 w-4 mr-2"/>
                                    Back to Logs
                                </Button>
                            </Link>
                            <div>
                                <h1 className="text-2xl font-bold">Backup Details</h1>
                                <p className="text-muted-foreground">
                                    {backupLog.data_source?.name} - {formatDate(backupLog.created_at)}
                                </p>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            {backupLog.is_file_available && (
                                <>
                                    <Button onClick={downloadBackup}>
                                        <Download className="h-4 w-4 mr-2"/>
                                        Download
                                    </Button>
                                    <Button 
                                        variant="destructive" 
                                        onClick={deleteBackupFile}
                                    >
                                        <Trash2 className="h-4 w-4 mr-2"/>
                                        Delete File
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Overview */}
                        <div className="lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Database className="h-5 w-5"/>
                                        Backup Overview
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Status</p>
                                            <Badge variant={getStatusVariant(backupLog.status)}>
                                                {backupLog.status}
                                            </Badge>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Type</p>
                                            <Badge variant={backupLog.type === 'Manual' ? 'default' : 'secondary'}>
                                                {backupLog.type}
                                            </Badge>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">File Size</p>
                                            <p className="text-sm">{backupLog.human_size || 'Unknown'}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Duration</p>
                                            <p className="text-sm">{backupLog.human_duration || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">File Status</p>
                                            <Badge variant={backupLog.file_deleted_at ? 'destructive' : 'default'}>
                                                {backupLog.file_deleted_at ? 'Deleted' : 'Available'}
                                            </Badge>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Storage Disk</p>
                                            <p className="text-sm">{backupLog.disk || 'local'}</p>
                                        </div>
                                    </div>

                                    {backupLog.filename && (
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Filename</p>
                                            <p className="text-sm font-mono">{backupLog.filename}</p>
                                        </div>
                                    )}

                                    {backupLog.file_path && (
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">File Path</p>
                                            <p className="text-sm font-mono">{backupLog.file_path}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Timeline */}
                            {backupLog.timelines && backupLog.timelines.length > 0 && (
                                <Card className="mt-6">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Clock className="h-5 w-5"/>
                                            Backup Timeline
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            {backupLog.timelines.map((timeline, index) => (
                                                <div key={timeline.id} className="flex items-start gap-3">
                                                    <div className="flex flex-col items-center">
                                                        {getTimelineIcon(timeline.status)}
                                                        {index < backupLog.timelines!.length - 1 && (
                                                            <div className="w-px h-8 bg-border mt-2"/>
                                                        )}
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant={getStatusVariant(timeline.status)}>
                                                                {timeline.status}
                                                            </Badge>
                                                            <span className="text-sm text-muted-foreground">
                                                                {formatShortDate(timeline.created_at)}
                                                            </span>
                                                            {timeline.human_duration_from_previous && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    (+{timeline.human_duration_from_previous})
                                                                </span>
                                                            )}
                                                        </div>
                                                        {timeline.metadata && Object.keys(timeline.metadata).length > 0 && (
                                                            <div className="text-xs text-muted-foreground mt-1">
                                                                {JSON.stringify(timeline.metadata, null, 2)}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        {/* Metadata & Errors */}
                        <div className="space-y-6">
                            {/* Data Source Info */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <HardDrive className="h-5 w-5"/>
                                        Data Source
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Name</p>
                                        <p className="text-sm">{backupLog.data_source?.name}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Database</p>
                                        <p className="text-sm">{backupLog.data_source?.database}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Host</p>
                                        <p className="text-sm">{backupLog.data_source?.host}:{backupLog.data_source?.port}</p>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Metadata */}
                            {backupLog.metadata && Object.keys(backupLog.metadata).length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Metadata</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <pre className="text-xs overflow-auto">
                                            {JSON.stringify(backupLog.metadata, null, 2)}
                                        </pre>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Warnings */}
                            {backupLog.warnings && backupLog.warnings.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-yellow-600">
                                            <AlertTriangle className="h-5 w-5"/>
                                            Warnings
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            {backupLog.warnings.map((warning, index) => (
                                                <div key={index} className="text-sm p-2 bg-yellow-50 border border-yellow-200 rounded">
                                                    {typeof warning === 'string' ? warning : JSON.stringify(warning)}
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Errors */}
                            {backupLog.errors && backupLog.errors.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-red-600">
                                            <AlertTriangle className="h-5 w-5"/>
                                            Errors
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            {backupLog.errors.map((error, index) => (
                                                <div key={index} className="text-sm p-2 bg-red-50 border border-red-200 rounded">
                                                    {typeof error === 'string' ? error : JSON.stringify(error)}
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}