import {DataTable} from '@/components/ui/data-table-server-side';
import {createDynamicColumns} from '@/components/ui/data-table/columns/column-factory';
import {route} from 'ziggy-js';
import {Head, router} from '@inertiajs/react';
import {PaginatedResponse} from '@/types/paginated-response';
import {BackupLog} from '@/types/backup-log';
import {DataSource} from '@/types/datasource';
import {Download, Trash2, Eye} from 'lucide-react';
import {toast} from 'sonner';
import MainLayout from '@/layouts/Main';
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

interface Props {
    backupLogs: PaginatedResponse<BackupLog>;
    dataSources: DataSource[];
}

export default function BackupLogsIndex({backupLogs, dataSources}: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [backupToDelete, setBackupToDelete] = useState<BackupLog | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const downloadBackup = async (backupLog: BackupLog) => {
        try {
            window.open(route('backup-logs.download', {backup_log: backupLog.id}), '_blank');
        } catch (error: any) {
            toast.error("Download error", {
                description: "An error occurred while downloading the backup file.",
            });
        }
    };

    const openDeleteDialog = (backupLog: BackupLog) => {
        setBackupToDelete(backupLog);
        setDeleteDialogOpen(true);
    };

    const closeDeleteDialog = () => {
        setDeleteDialogOpen(false);
        setBackupToDelete(null);
        setIsDeleting(false);
    };

    const confirmDeleteBackupFile = () => {
        if (!backupToDelete) return;

        setIsDeleting(true);
        router.delete(route('backup-logs.delete-file', {backup_log: backupToDelete.id}), {
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
                    description: "Backup file deleted successfully. Log entry preserved.",
                });
                closeDeleteDialog();
            }
        });
    };

    const tableActions = () => {
        return {
            additionalActions: [
                {
                    type: 'command' as const,
                    label: 'View Details',
                    action: (row: BackupLog) => {
                        router.visit(route('backup-logs.show', {backup_log: row.id}));
                    },
                    icon: <Eye className="h-4 w-4"/>,
                    placement: 'inline' as const,
                    order: 'beginning' as const,
                },
                {
                    type: 'command' as const,
                    label: 'Download',
                    action: (row: BackupLog) => downloadBackup(row),
                    icon: <Download className="h-4 w-4"/>,
                    placement: 'inline' as const,
                    disabled: (row: BackupLog) => !row.is_file_available,
                    order: 'beginning' as const,
                },
                {
                    type: 'command' as const,
                    label: 'Delete File',
                    action: (row: BackupLog) => openDeleteDialog(row),
                    icon: <Trash2 className="h-4 w-4"/>,
                    placement: 'inline' as const,
                    disabled: (row: BackupLog) => !row.is_file_available,
                    order: 'end' as const,
                },
            ],
        };
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

    const getTypeVariant = (type: string) => {
        return type === 'Manual' ? 'default' : 'secondary';
    };

    const renderDataSource = (_value: any, row: BackupLog) => {
        return row.data_source?.name || 'Unknown';
    };

    const renderDuration = (_value: any, row: BackupLog) => {
        return row.human_duration || 'N/A';
    };

    const renderFileStatus = (_value: any, row: BackupLog) => {
        if (row.file_deleted_at) {
            return 'Deleted';
        }
        return row.is_file_available ? 'Available' : 'N/A';
    };

    const getFileStatusVariant = (row: BackupLog) => {
        if (row.file_deleted_at) {
            return 'destructive';
        }
        return row.is_file_available ? 'default' : 'secondary';
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);

        const day = String(date.getDate()).padStart(2, '0');
        const month = date.toLocaleString('default', {month: 'short'});
        const year = date.getFullYear();

        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${day}/${month}/${year} ${hours}:${minutes}`;
    };

    return (
        <MainLayout>
            <Head title="Backup Logs"/>

            <div className="flex flex-1 flex-col gap-4 p-25 pt-0 mt-4">
                <div className="mx-4">
                    <DataTable
                        pageTitle="Backup Logs"
                        data={backupLogs}
                        enableSearch={true}
                        columns={createDynamicColumns<BackupLog>([
                            {
                                label: "Data Source",
                                name: "data_source",
                                type: "text",
                                sortable: true,
                                transform: renderDataSource,
                                className: "font-medium",
                            },
                            {
                                label: "Status",
                                name: "status",
                                type: "badge",
                                sortable: true,
                                transformVariant: (value: string) => getStatusVariant(value),
                            },
                            {
                                label: "Type",
                                name: "type",
                                type: "badge",
                                sortable: true,
                                transformVariant: (value: string) => getTypeVariant(value),
                            },
                            {
                                label: "Size",
                                name: "human_size",
                                type: "text",
                                sortable: false,
                            },
                            {
                                label: "Duration",
                                name: "human_duration",
                                type: "text",
                                sortable: false,
                                transform: renderDuration,
                            },
                            {
                                label: "File Status",
                                name: "file_deleted_at",
                                type: "badge",
                                sortable: false,
                                transform: renderFileStatus,
                                transformVariant: (_value: any, row: BackupLog) => getFileStatusVariant(row),
                            },
                            {
                                label: "Created",
                                name: "created_at",
                                type: "text",
                                sortable: true,
                                transform: (value: string) => formatDate(value),
                            },
                        ])}
                        actions={tableActions()}
                        filters={[
                            {
                                type: 'select',
                                identifier: 'data_source_id',
                                label: 'Data Source',
                                options: dataSources.map(ds => ({
                                    label: ds.name,
                                    value: ds.id
                                }))
                            },
                            {
                                type: 'select',
                                identifier: 'status',
                                label: 'Status',
                                options: [
                                    {label: "Pending", value: "Pending"},
                                    {label: "Running", value: "Running"},
                                    {label: "Completed", value: "Completed"},
                                    {label: "Failed", value: "Failed"},
                                ]
                            },
                            {
                                type: 'select',
                                identifier: 'type',
                                label: 'Type',
                                options: [
                                    {label: "Manual", value: "Manual"},
                                    {label: "Automated", value: "Automated"},
                                ]
                            },
                            {
                                type: 'date',
                                identifier: 'date_from',
                                label: 'Date From',
                            },
                            {
                                type: 'date',
                                identifier: 'date_to',
                                label: 'Date To',
                            },
                        ]}
                    />
                </div>
            </div>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Backup File</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this backup file? The log entry will be preserved but the file will be permanently deleted.
                            {backupToDelete && `\n\nData Source: ${backupToDelete.data_source?.name}`}
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
