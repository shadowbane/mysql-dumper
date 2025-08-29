import {DataTable} from '@/components/ui/data-table-server-side';
import {createDynamicColumns} from '@/components/ui/data-table/columns/column-factory';
import {route} from 'ziggy-js';
import {Head} from '@inertiajs/react';
import {PaginatedResponse} from '@/types/paginated-response';
import {BackupLog} from '@/types/backup-log';
import {DataSource} from '@/types/datasource';
import MainLayout from '@/layouts/Main';
import {format} from 'date-fns';

interface Props {
    backupLogs: PaginatedResponse<BackupLog>;
    dataSources: DataSource[];
}

export default function BackupLogsIndex({backupLogs, dataSources}: Props) {
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
        if (row.files?.length === 0 && row.status === 'Completed') {
            return 'Deleted';
        }
        return row.is_file_available ? 'Available' : 'N/A';
    };

    const getFileStatusVariant = (row: BackupLog) => {
        if (row.files?.length === 0 && row.status === 'Completed') {
            return 'destructive';
        }
        return row.is_file_available ? 'default' : 'secondary';
    };

    return (
        <MainLayout>
            <Head title="Backup Logs"/>

            <div className="flex flex-1 flex-col gap-4 p-25 pt-0 mt-4">
                <div className="mx-4">
                    <DataTable
                        pageTitle="Backup Logs"
                        data={backupLogs}
                        enableSearch={false}
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
                                sortable: false,
                                transformVariant: (value: string) => getStatusVariant(value),
                            },
                            {
                                label: "Type",
                                name: "type",
                                type: "badge",
                                sortable: false,
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
                                label: "Locked",
                                name: "locked",
                                type: "badge",
                                sortable: false,
                                transform: (value: boolean) => (value ? 'Locked' : 'Unlocked'),
                                transformVariant: (value: boolean) => (value ? 'default' : 'secondary'),
                            },
                            {
                                label: "Created",
                                name: "created_at",
                                type: "text",
                                sortable: true,
                                transform: (value: string) => format(new Date(value), 'dd LLL y HH:mm:ss'),
                            },
                        ])}
                        filters={[
                            {
                                type: 'date_range',
                                identifier: 'date',
                                label: 'Date Range',
                                options: {
                                    timePicker: true,
                                    timePickerIncrement: 30,
                                    showDropdowns: true,
                                }
                            },
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
                                type: 'select',
                                identifier: 'locked',
                                label: 'Locked',
                                options: [
                                    {label: "Unlocked", value: 0},
                                    {label: "Locked", value: 1},
                                ]
                            },
                        ]}
                        rowClick={{
                            enabled: true,
                            action: 'route',
                            route: route('backup-logs.index'),
                            routeParam: 'id', // Use the 'id' field from each row for the route parameter
                            stopPropagation: true,
                            excludeClickOnColumns: ['actions', 'expander'],
                            // Example using custom action:
                            // action: 'function',
                            // onClick: handleJournalRowClick,
                        }}
                    />
                </div>
            </div>
        </MainLayout>
    );
}
