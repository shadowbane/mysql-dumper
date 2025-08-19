import {DataTable} from '@/components/ui/data-table-server-side';
import {createDynamicColumns} from '@/components/ui/data-table/columns/column-factory';
import {route} from 'ziggy-js';
import {Head} from '@inertiajs/react';
import {PaginatedResponse} from '@/types/paginated-response';
import {DataSource} from '@/types/datasource';
import {Plug2, Download} from 'lucide-react';
import {toast} from 'sonner';
import MainLayout from '@/layouts/Main';
import axios from 'axios';
import {triggerBackup} from "@/components/functions/backups";

interface Props {
    dataSources: PaginatedResponse<DataSource>;
}

export default function DataSourcesIndex({dataSources}: Props) {
    console.log(dataSources);

    const testConnection = async (dataSource: DataSource) => {
        try {
            const response = await axios.post(route('data-sources.test', {
                data_source: dataSource.id,
            }));

            if (response.data.success) {
                toast.success("Connection successful", {
                    description: "Successfully connected to the database.",
                });
            } else {
                toast.error("Connection failed", {
                    description: response.data.message || "Failed to connect to the database.",
                });
            }
        } catch (error: any) {
            toast.error("Connection error", {
                description: error.response?.data?.message || "An error occurred while testing the connection.",
            });
        }
    };

    const tableActions = () => {
        return {
            create: {
                type: 'route' as const,
                action: route('data-sources.create'),
                label: 'Add Data Source',
            },
            edit: {
                enabled: true,
                baseUrl: '/data-sources',
            },
            delete: {
                enabled: true,
                baseUrl: '/data-sources',
                name: 'data source',
                value: 'name',
            },
            additionalActions: [
                {
                    type: 'command' as const,
                    label: 'Test Connection',
                    action: (row: DataSource) => testConnection(row),
                    icon: <Plug2 className="h-4 w-4"/>,
                    placement: 'inline' as const,
                    order: 'beginning',
                },
                {
                    type: 'command' as const,
                    label: 'Backup Now',
                    action: (row: DataSource) => triggerBackup(row),
                    icon: <Download className="h-4 w-4"/>,
                    placement: 'inline' as const,
                    disabled: (row: DataSource) => ["Pending", "Running"].includes(row.latest_backup_log?.status), //row.latest_backup_log?.status === 'Running',
                    order: 'beginning',
                },
            ],
        };
    };

    const getStatusVariant = (isActive: boolean) => {
        return isActive ? 'default' : 'secondary';
    };


    const renderConnection = (_value: string, row: DataSource) => {
        return `${row.host}:${row.port} (${row.database})`;
    };

    const renderBackupStatus = (_value: any, row: DataSource) => {
        const latestBackup = row.latest_backup_log;
        const isHealthy = row.is_backup_healthy;

        if (!latestBackup) {
            return 'No Backup';
        }

        const formatDate = (dateString: string | null) => {
            if (!dateString) return 'Never';
            const date = new Date(dateString);

            const day = String(date.getDate()).padStart(2, '0');
            const month = date.toLocaleString('default', {month: 'short'});
            const year = date.getFullYear();

            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');

            return `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
        };

        switch (latestBackup.status) {
            case 'Completed':
                return `${isHealthy ? 'Healthy' : 'Stale'} - ${formatDate(latestBackup.completed_at)}`;
            case 'Running':
                return 'Running...';
            case 'Failed':
                return `Failed - ${formatDate(latestBackup.completed_at)}`;
            case 'Pending':
                return 'Pending';
            default:
                return 'Unknown';
        }
    };

    const getBackupBadgeVariant = (row: DataSource) => {
        const latestBackup = row.latest_backup_log;
        const isHealthy = row.is_backup_healthy;

        if (!latestBackup) {
            return 'secondary';
        }

        switch (latestBackup.status) {
            case 'Completed':
                return isHealthy ? 'default' : 'secondary';
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


    return (
        <MainLayout>
            <Head title="Data Sources"/>

            <div className="flex flex-1 flex-col gap-4 p-25 pt-0 mt-4">
                <div className="mx-4">
                    <DataTable
                        pageTitle="Data Sources"
                        data={dataSources}
                        enableSearch={true}
                        columns={createDynamicColumns<DataSource>([
                            {
                                label: "Name",
                                name: "name",
                                type: "text",
                                sortable: true,
                                className: "font-medium",
                            },
                            {
                                label: "Connection",
                                name: "host",
                                type: "text",
                                transform: renderConnection,
                            },
                            {
                                label: "Status",
                                name: "is_active",
                                type: "badge",
                                sortable: true,
                                transform: (value: boolean) => value ? "Active" : "Inactive",
                                transformVariant: (value: boolean) => getStatusVariant(value),
                            },
                            {
                                label: "Backup Status",
                                name: "latest_backup_log",
                                type: "badge",
                                transform: renderBackupStatus,
                                transformVariant: (_value: any, row: DataSource) => getBackupBadgeVariant(row),
                                className: "min-w-[200px]",
                            },
                        ])}
                        actions={tableActions()}
                        filters={[
                            {
                                type: 'select',
                                identifier: 'is_active',
                                label: 'Status',
                                options: [
                                    {label: "Active", value: "1"},
                                    {label: "Inactive", value: "0"},
                                ]
                            },
                            {
                                type: 'select',
                                identifier: 'backup_status',
                                label: 'Backup Status',
                                options: [
                                    {label: "Healthy", value: "healthy"},
                                    {label: "Stale", value: "stale"},
                                    {label: "Failed", value: "failed"},
                                    {label: "No Backup", value: "none"},
                                ]
                            },
                        ]}
                    />
                </div>
            </div>
        </MainLayout>
    );
}
