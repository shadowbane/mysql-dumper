import React from 'react';
import {Head, router} from '@inertiajs/react';
import {ArrowLeft} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Link} from '@inertiajs/react';
import MainLayout from '@/layouts/Main';
import DataSourceForm from "@/components/Custom/data-source/DataSourceForm";
import {DataSource} from '@/types/datasource';
import {toast} from 'sonner';
import {route} from 'ziggy-js';

interface Props {
    dataSource: DataSource;
}

export default function Edit({dataSource}: Props) {
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
            <Head title={`Edit ${dataSource.name}`}/>

            <div className="flex flex-1 flex-col gap-4 p-4 pt-0 mt-4">
                <div className="mx-4">
                    <div className="mb-4">
                        <Link href={route('data-sources.index')}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="h-4 w-4 mr-2"/>
                                Back to Data Sources
                            </Button>
                        </Link>
                    </div>

                    <div className="max-w-4xl mx-auto">
                        <div className="mb-6">
                            <h1 className="text-2xl font-bold">Edit Data Source</h1>
                            <p className="text-muted-foreground">
                                Modify the database connection "{dataSource.name}"
                            </p>
                        </div>
                        <DataSourceForm
                            dataSource={dataSource}
                            onSubmit={handleSubmit}
                            isEditing={true}
                        />
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
