import React from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/layouts/Main';
import DataSourceForm from '@/components/DataSourceForm';
import { toast } from 'sonner';

export default function Create() {
    const handleSubmit = (data: any) => {
        router.post('/data-sources', data, {
            onSuccess: () => {
                toast.success('Data source created successfully');
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
            },
        });
    };

    return (
        <MainLayout>
            <Head title="Create Data Source" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 lg:gap-6 lg:p-6">
                <div className="flex items-center">
                    <h1 className="text-lg font-semibold md:text-2xl">Create Data Source</h1>
                </div>

                <div className="bg-background rounded-lg border shadow-sm p-6">
                    <DataSourceForm onSubmit={handleSubmit} />
                </div>
            </div>
        </MainLayout>
    );
}