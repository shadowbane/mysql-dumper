import React from 'react';
import {Head, router} from '@inertiajs/react';
import {ArrowLeft} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Link} from '@inertiajs/react';
import MainLayout from '@/layouts/Main';
import DataSourceForm from "@/components/Custom/data-source/DataSourceForm";
import {toast} from 'sonner';
import {route} from 'ziggy-js';

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
            <Head title="Create Data Source"/>

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
                            <h1 className="text-2xl font-bold">Create Data Source</h1>
                            <p className="text-muted-foreground">
                                Add a new database connection for backups
                            </p>
                        </div>
                        <DataSourceForm onSubmit={handleSubmit}/>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
