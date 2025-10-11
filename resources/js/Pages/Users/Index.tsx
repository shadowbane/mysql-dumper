import {DataTable} from '@/components/ui/data-table-server-side';
import {createDynamicColumns} from '@/components/ui/data-table/columns/column-factory';
import {route} from 'ziggy-js';
import {Head} from '@inertiajs/react';
import {PaginatedResponse} from '@/types/paginated-response';
import {User} from '@/types/user';
import {Role} from '@/types/user';
import MainLayout from '@/layouts/Main';

interface Props {
    users: PaginatedResponse<User & { roles: Role[] }>;
    roles: Role[];
}

export default function UsersIndex({users, roles}: Props) {
    const tableActions = () => {
        return {
            create: {
                type: 'route' as const,
                action: route('user.create'),
                label: 'Add User',
            },
            edit: {
                enabled: true,
                baseUrl: '/user',
            },
            delete: {
                enabled: true,
                baseUrl: '/user',
                name: 'user',
                value: 'name',
            },
        };
    };

    const renderRoles = (_value: any, row: User & { roles: Role[] }) => {
        if (!row.roles || row.roles.length === 0) {
            return 'No Roles';
        }
        return row.roles.map(role => role.name).join(', ');
    };

    const getRoleBadgeVariant = (row: User & { roles: Role[] }) => {
        if (!row.roles || row.roles.length === 0) {
            return 'secondary';
        }

        // If user has administrator role, use default variant
        const isAdmin = row.roles.some(role => role.slug === 'administrator');
        return isAdmin ? 'default' : 'outline';
    };

    return (
        <MainLayout>
            <Head title="Users"/>

            <div className="flex flex-1 flex-col gap-4 p-25 pt-0 mt-4">
                <div className="mx-4">
                    <DataTable
                        pageTitle="Users"
                        data={users}
                        enableSearch={true}
                        columns={createDynamicColumns<User & { roles: Role[] }>([
                            {
                                label: "Name",
                                name: "name",
                                type: "text",
                                sortable: true,
                                className: "font-medium",
                            },
                            {
                                label: "Email",
                                name: "email",
                                type: "text",
                                sortable: true,
                            },
                            {
                                label: "Roles",
                                name: "roles",
                                type: "badge",
                                transform: renderRoles,
                                transformVariant: (_value: any, row: User & { roles: Role[] }) => getRoleBadgeVariant(row),
                            },
                        ])}
                        actions={tableActions()}
                        filters={[
                            {
                                type: 'select',
                                identifier: 'role',
                                label: 'Role',
                                options: roles.map(role => ({
                                    label: role.name,
                                    value: role.id!.toString(),
                                }))
                            },
                        ]}
                    />
                </div>
            </div>
        </MainLayout>
    );
}