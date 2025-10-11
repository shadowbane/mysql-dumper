import React from 'react';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import MainLayout from '@/layouts/Main';
import UserForm from "@/components/Custom/user/UserForm";
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import { Role } from '@/types/user';
import { DataSource } from '@/types/datasource';

interface Props {
    roles: Role[];
    dataSources: DataSource[];
    errors?: { [key: string]: string };
}

export default function Create({ roles, dataSources, errors }: Props) {
    const handleSubmit = (data: any) => {
        router.post(route('user.store'), data, {
            onSuccess: () => {
                toast.success('User created successfully');
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
            },
        });
    };

    return (
        <MainLayout>
            <Head title="Create User"/>

            <div className="flex flex-1 flex-col gap-4 p-4 pt-0 mt-4">
                <div className="mx-4">
                    <div className="mb-4">
                        <Link href={route('user.index')}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="h-4 w-4 mr-2"/>
                                Back to Users
                            </Button>
                        </Link>
                    </div>

                    <div className="max-w-4xl mx-auto">
                        <div className="mb-6">
                            <h1 className="text-2xl font-bold">Create User</h1>
                            <p className="text-muted-foreground">
                                Add a new user with role-based permissions
                            </p>
                        </div>
                        <UserForm
                            roles={roles}
                            dataSources={dataSources}
                            onSubmit={handleSubmit}
                            errors={errors ?? {}}
                        />
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}