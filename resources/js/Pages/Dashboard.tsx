import { Head } from '@inertiajs/react';

import MainLayout from '@/layouts/Main';

export default function Dashboard() {
    return (
        <MainLayout>
            <Head title="Dashboard" />
            <div className="container max-w-screen-2xl">
                <div className="flex h-full flex-1 flex-col gap-4 p-4 lg:gap-6 lg:p-6">
                    <div className="flex items-center">
                        <h1 className="text-lg font-semibold md:text-2xl">Dashboard</h1>
                    </div>
                    <div className="flex flex-1 items-center justify-center rounded-lg border border-dashed shadow-sm">
                        <div className="flex flex-col items-center gap-1 text-center">
                            <h3 className="text-2xl font-bold tracking-tight">Welcome to your dashboard!</h3>
                            <p className="text-sm text-muted-foreground">
                                This is where you can manage your database backups.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
