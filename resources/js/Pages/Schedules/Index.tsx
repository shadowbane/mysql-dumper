import {DataTable} from '@/components/ui/data-table-server-side';
import {createDynamicColumns} from '@/components/ui/data-table/columns/column-factory';
import {route} from 'ziggy-js';
import {Head, router} from '@inertiajs/react';
import {PaginatedResponse} from '@/types/paginated-response';
import {Schedule} from '@/types/schedule';
import {DataSource} from '@/types/datasource';
import {Calendar, Play} from 'lucide-react';
import {toast} from 'sonner';
import MainLayout from '@/layouts/Main';

interface Props {
    schedules: PaginatedResponse<Schedule>;
    dataSources: DataSource[];
}

export default function SchedulesIndex({schedules, dataSources}: Props) {
    const toggleScheduleStatus = async (schedule: Schedule) => {
        try {
            router.put(route('schedules.update', {schedule: schedule.id}), {
                name: schedule.name,
                description: schedule.description,
                hour: schedule.hour,
                days_of_week: schedule.days_of_week,
                is_active: !schedule.is_active,
                data_source_ids: schedule.data_sources?.map(ds => ds.id!) || [],
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        schedule.is_active ? "Schedule deactivated" : "Schedule activated",
                        {
                            description: `Schedule "${schedule.name}" has been ${schedule.is_active ? 'deactivated' : 'activated'}.`,
                        }
                    );
                },
                onError: (errors: any) => {
                    toast.error("Update failed", {
                        description: Object.values(errors).flat().join(', '),
                    });
                },
            });
        } catch (error: any) {
            toast.error("Error", {
                description: "An error occurred while updating the schedule.",
            });
        }
    };

    const tableActions = () => {
        return {
            create: {
                type: 'route' as const,
                label: 'Add Schedule',
                action: route('schedules.create'),
                icon: <Calendar className="h-4 w-4"/>,
            },
            preview: {
                enabled: true,
                baseUrl: route('schedules.index'),
                label: 'View Details',
            },
            edit: {
                enabled: true,
                baseUrl: route('schedules.index'),
                label: 'Edit Schedule',
            },
            delete: {
                enabled: true,
                baseUrl: route('schedules.index'),
                name: 'schedule',
                value: (row: Schedule) => row.name,
                label: 'Delete Schedule',
            },
            additionalActions: [
                {
                    type: 'command' as const,
                    label: 'Toggle Status',
                    action: (row: Schedule) => toggleScheduleStatus(row),
                    icon: <Play className="h-4 w-4"/>,
                    placement: 'inline' as const,
                    order: 'beginning' as const,
                },
            ],
        };
    };

    const getStatusVariant = (isActive: boolean) => {
        return isActive ? 'default' : 'secondary';
    };

    const renderDataSources = (_value: any, row: Schedule) => {
        const count = row.data_sources?.length || 0;
        const names = row.data_sources?.slice(0, 2).map(ds => ds.name).join(', ') || '';
        const remaining = count > 2 ? ` +${count - 2} more` : '';
        return count > 0 ? `${names}${remaining}` : 'None';
    };

    const renderLastRun = (_value: any, row: Schedule) => {
        if (!row.last_run_at) return 'Never';
        const date = new Date(row.last_run_at);
        return date.toLocaleString();
    };

    const formatDateTime = (dateString: string | null) => {
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
            <Head title="Schedules"/>

            <div className="flex flex-1 flex-col gap-4 p-25 pt-0 mt-4">
                <div className="mx-4">
                    <DataTable
                        pageTitle="Backup Schedules"
                        data={schedules}
                        enableSearch={true}
                        columns={createDynamicColumns<Schedule>([
                            {
                                label: "Name",
                                name: "name",
                                type: "text",
                                sortable: true,
                                className: "font-medium",
                            },
                            {
                                label: "Schedule",
                                name: "human_days",
                                type: "text",
                                sortable: false,
                                transform: (_value: any, row: Schedule) =>
                                    `${row.human_days} at ${row.human_time}`,
                            },
                            {
                                label: "Data Sources",
                                name: "data_sources",
                                type: "text",
                                sortable: false,
                                transform: renderDataSources,
                                className: "text-sm",
                            },
                            {
                                label: "Status",
                                name: "is_active",
                                type: "badge",
                                sortable: true,
                                transform: (value: boolean) => value ? 'Active' : 'Inactive',
                                transformVariant: (value: boolean) => getStatusVariant(value),
                            },
                            {
                                label: "Last Run",
                                name: "last_run_at",
                                type: "text",
                                sortable: true,
                                transform: renderLastRun,
                            },
                            {
                                label: "Created",
                                name: "created_at",
                                type: "text",
                                sortable: true,
                                transform: (value: string) => formatDateTime(value),
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
                                    value: ds.id!
                                }))
                            },
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
