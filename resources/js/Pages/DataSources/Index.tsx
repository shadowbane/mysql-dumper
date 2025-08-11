import { Head } from '@inertiajs/react';

import MainLayout from '@/layouts/Main';

export default function Index() {
    return (
        <MainLayout>
            <Head title="Data Sources" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4 lg:gap-6 lg:p-6">
                <div className="flex items-center">
                    <h1 className="text-lg font-semibold md:text-2xl">Data Sources</h1>
                </div>
                <div className="flex flex-1 items-center justify-center rounded-lg border border-dashed shadow-sm">
                    <div className="flex flex-col items-center gap-1 text-center">
                        <h3 className="text-2xl font-bold tracking-tight">No data sources yet</h3>
                        <p className="text-sm text-muted-foreground">
                            You can start by adding a new data source.
                        </p>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
