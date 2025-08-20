import {Head, Link, router} from '@inertiajs/react';
import {BackupLog} from '@/types/backup-log';
import {File} from '@/types/file';
import {ArrowLeft, Download, Trash2, Database, Clock, HardDrive, AlertTriangle, CheckCircle} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Card, CardContent, CardHeader, CardTitle} from '@/components/ui/card';
import {Badge} from '@/components/ui/badge';
import {toast} from 'sonner';
import MainLayout from '@/layouts/Main';
import {route} from 'ziggy-js';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {useState} from 'react';
import { format } from 'date-fns';

interface Props {
    backupLog: BackupLog;
}

export default function BackupLogShow({backupLog}: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [fileToDelete, setFileToDelete] = useState<File | null>(null);

    const downloadIndividualFile = async (file: File) => {
        try {
            window.open(route('backup-logs.files.download', {backup_log: backupLog.id, file: file.id}), '_blank');
        } catch (error: any) {
            toast.error("Download error", {
                description: "An error occurred while downloading the backup file.",
            });
        }
    };

    const openIndividualDeleteDialog = (file: File) => {
        setFileToDelete(file);
        setDeleteDialogOpen(true);
    };

    const closeDeleteDialog = () => {
        setDeleteDialogOpen(false);
        setIsDeleting(false);
        setFileToDelete(null);
    };

    const confirmDeleteBackupFile = () => {
        if (!fileToDelete) return;

        setIsDeleting(true);
        router.delete(route('backup-logs.files.delete', {backup_log: backupLog.id, file: fileToDelete.id}), {
            preserveScroll: true,
            onError: (errors: any) => {
                const errorMessage = errors?.message || errors?.error || errors?.[0] || 'An error occurred while deleting the backup file.';
                toast.error("Delete error", {
                    description: errorMessage,
                });
                closeDeleteDialog();
            },
            onSuccess: () => {
                toast.success("File deleted", {
                    description: "Backup file deleted successfully.",
                });
                closeDeleteDialog();
                // Reload the page to reflect changes
                router.reload({only: ['backupLog']});
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

    const formatShortDate = (dateString: string | null) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleTimeString();
    };

    const formatError = (error: any) => {
        if (typeof error === 'string') {
            return error;
        }

        if (typeof error === 'object' && error !== null) {
            // If it's an error object with message, show formatted details
            if (error.message) {
                let formattedError = `Error: ${error.message}`;

                if (error.file && error.line) {
                    formattedError += `\nFile: ${error.file}:${error.line}`;
                }

                if (error.code && error.code !== 0) {
                    formattedError += `\nCode: ${error.code}`;
                }

                if (error.context) {
                    formattedError += `\nContext: ${JSON.stringify(error.context, null, 2)}`;
                }

                return formattedError;
            }

            // For other objects, stringify but make it readable
            return JSON.stringify(error, null, 2);
        }

        return String(error);
    };

    const formatWarning = (warning: any) => {
        if (typeof warning === 'string') {
            return warning;
        }

        if (typeof warning === 'object' && warning !== null) {
            // If it has a message property, use that
            if (warning.message) {
                return warning.message;
            }

            // For other objects, stringify
            return JSON.stringify(warning, null, 2);
        }

        return String(warning);
    };

    return (
        <MainLayout>
            <Head title={`Backup Log - ${backupLog.data_source?.name}`}/>

            <div className="flex flex-1 flex-col gap-4 p-4 pt-0 mt-4">
                <div className="mx-4">
                    <div className="mb-4">
                        <Link href={route('backup-logs.index')}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="h-4 w-4 mr-2"/>
                                Back to Logs
                            </Button>
                        </Link>
                    </div>

                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold">Backup Details</h1>
                            <p className="text-muted-foreground">
                                {backupLog.data_source?.name} - {format(backupLog.created_at, 'd MMMM y')}, {formatShortDate(backupLog.created_at)}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {backupLog.files && backupLog.files.length > 0 && (
                                <>
                                    {backupLog.files.length === 1 && (
                                        <>
                                            <Button onClick={() => downloadIndividualFile(backupLog.files![0])}>
                                                <Download className="h-4 w-4 mr-2"/>
                                                Download
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                onClick={() => openIndividualDeleteDialog(backupLog.files![0])}
                                            >
                                                <Trash2 className="h-4 w-4 mr-2"/>
                                                Delete File
                                            </Button>
                                        </>
                                    )}
                                    {backupLog.files.length > 1 && (
                                        <p className="text-sm text-muted-foreground">
                                            Multiple files available (see Files section below)
                                        </p>
                                    )}
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
                                            <p className="text-sm font-medium text-muted-foreground">Files Count</p>
                                            <p className="text-sm">{backupLog.files?.length || 0} file(s)</p>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">Files Status</p>
                                            <Badge variant={backupLog.files && backupLog.files.length > 0 ? 'default' : 'secondary'}>
                                                {backupLog.files && backupLog.files.length > 0 ? 'Available' : 'No files'}
                                            </Badge>
                                        </div>
                                    </div>
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
                                                                {format(timeline.created_at, 'd MMMM y')}, {formatShortDate(timeline.created_at)}
                                                            </span>
                                                            {timeline.human_duration_from_previous && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    (+{timeline.human_duration_from_previous})
                                                                </span>
                                                            )}
                                                        </div>
                                                        {/*{timeline.metadata && Object.keys(timeline.metadata).length > 0 && (*/}
                                                        {/*    <div className="text-xs text-muted-foreground mt-1">*/}
                                                        {/*        {JSON.stringify(timeline.metadata, null, 2)}*/}
                                                        {/*    </div>*/}
                                                        {/*)}*/}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Files */}
                            {backupLog.files && backupLog.files.length > 0 && (
                                <Card className="mt-6">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <HardDrive className="h-5 w-5"/>
                                            Backup Files ({backupLog.files.length})
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            {backupLog.files.map((file) => (
                                                <div key={file.id} className="flex items-center justify-between p-3 border rounded-lg">
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <p className="text-sm font-medium truncate">{file.filename}</p>
                                                            <Badge variant="outline" className="text-xs">
                                                                {file.disk || 'local'}
                                                            </Badge>
                                                        </div>
                                                        <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                                            <span>{file.human_size || 'Unknown size'}</span>
                                                        </div>
                                                        {file.path && (
                                                            <p className="text-xs text-muted-foreground font-mono mt-1 truncate">
                                                                {file.path}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-2 ml-4">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => downloadIndividualFile(file)}
                                                        >
                                                            <Download className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => openIndividualDeleteDialog(file)}
                                                        >
                                                            <Trash2 className="h-4 w-4 text-destructive" />
                                                        </Button>
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
                                                <div key={index} className="text-sm p-2 bg-yellow-50 border border-yellow-200 rounded whitespace-pre-wrap">
                                                    {formatWarning(warning)}
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
                                                <div key={index} className="text-sm p-2 bg-red-50 border border-red-200 rounded whitespace-pre-wrap">
                                                    {formatError(error)}
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

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Backup File</AlertDialogTitle>
                        <AlertDialogDescription className="whitespace-pre-wrap">
                            Are you sure you want to delete this backup file? The file will be permanently deleted.
                            {'\n'}Data Source: {backupLog.data_source?.name}
                            {'\n'}Disk: {fileToDelete?.disk}
                            {fileToDelete && `\nFile: ${fileToDelete.path}`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={closeDeleteDialog} disabled={isDeleting}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDeleteBackupFile}
                            disabled={isDeleting}
                            className="bg-red-600 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 text-white"
                        >
                            {isDeleting ? 'Deleting...' : 'Delete File'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
