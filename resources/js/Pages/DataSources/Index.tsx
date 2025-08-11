import {DataTable} from '@/components/ui/data-table-server-side';
import {createDynamicColumns} from '@/components/ui/data-table/columns/column-factory';
import {route} from 'ziggy-js';
import {Head} from '@inertiajs/react';
import {PaginatedResponse} from '@/types/paginated-response';
import {DataSource} from '@/types/datasource';
import {Plug2} from 'lucide-react';
import {toast} from 'sonner';
import MainLayout from '@/layouts/Main';

interface Props {
    dataSources: PaginatedResponse<DataSource>;
}

export default function DataSourcesIndex({dataSources}: Props) {

    const testConnection = async (dataSource: DataSource) => {
        try {
            const response = await fetch(`/data-sources/${dataSource.id}/test-connection`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const result = await response.json();

            if (result.success) {
                toast.success("Connection successful", {
                    description: "Successfully connected to the database.",
                });
            } else {
                toast.error("Connection failed", {
                    description: result.message || "Failed to connect to the database.",
                });
            }
        } catch (error) {
            toast.error("Connection error", {
                description: "An error occurred while testing the connection.",
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
                },
            ],
        };
    };

    const getStatusVariant = (isActive: boolean) => {
        return isActive ? 'default' : 'secondary';
    };

    const renderSkippedTables = (skippedTables: string | string[] | null) => {
        if (!skippedTables || (Array.isArray(skippedTables) && skippedTables.length === 0)) {
            return 'None';
        }

        const tables = typeof skippedTables === 'string'
            ? skippedTables.split(',').map(t => t.trim())
            : skippedTables;

        if (tables.length <= 2) {
            return tables.join(', ');
        }

        return `${tables.slice(0, 2).join(', ')} +${tables.length - 2} more`;
    };

    const renderConnection = (_value: string, row: DataSource) => {
        return `${row.host}:${row.port} (${row.database})`;
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
                                label: "Username",
                                name: "username",
                                type: "text",
                                className: "font-mono text-sm",
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
                                label: "Skipped Tables",
                                name: "skipped_tables",
                                type: "text",
                                transform: (value: string | string[] | null) => renderSkippedTables(value),
                            },
                            {
                                label: "Created",
                                name: "created_at",
                                type: "date",
                                sortable: true,
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
                        ]}
                    />
                </div>
            </div>
        </MainLayout>
    );
}
