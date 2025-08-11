import React from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/layouts/Main';
import DataSourceForm from '@/components/DataSourceForm';
import { DataSource } from '@/types/datasource';
import { toast } from 'sonner';

interface Props {
    dataSource: DataSource;
}

export default function Edit({ dataSource }: Props) {
    const handleSubmit = (data: any) => {
        router.put(`/data-sources/${dataSource.id}`, data, {
            onSuccess: () => {
                toast.success('Data source updated successfully');
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
            },
        });
    };

    return (
        <MainLayout>
            <Head title={`Edit ${dataSource.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 lg:gap-6 lg:p-6">
                <div className="flex items-center">
                    <h1 className="text-lg font-semibold md:text-2xl">
                        Edit Data Source: {dataSource.name}
                    </h1>
                </div>

                <div className="bg-background rounded-lg border shadow-sm p-6">
                    <DataSourceForm 
                        dataSource={dataSource}
                        onSubmit={handleSubmit}
                        isEditing={true}
                    />
                </div>
            </div>
        </MainLayout>
    );
}