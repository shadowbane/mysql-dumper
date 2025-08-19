import React from 'react';
import {Head, router} from '@inertiajs/react';
import {ArrowLeft} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Link} from '@inertiajs/react';
import MainLayout from '@/layouts/Main';
import ScheduleForm from "@/components/Custom/schedules/ScheduleForm";
import {DataSource} from '@/types/datasource';
import {ScheduleFormData} from '@/schemas/scheduleSchema';
import {toast} from 'sonner';
import {route} from 'ziggy-js';

interface Props {
    dataSources: DataSource[];
}

export default function CreateSchedule({dataSources}: Props) {
    const handleSubmit = (data: ScheduleFormData) => {
        router.post(route('schedules.store'), data, {
            onSuccess: () => {
                toast.success('Schedule created successfully', {
                    description: 'The backup schedule has been created and is ready to use.',
                });
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
                // The form will display field-specific errors automatically
                toast.error('Validation failed', {
                    description: 'Please check the form for errors and try again.',
                });
            },
        });
    };

    return (
        <MainLayout>
            <Head title="Create Schedule"/>

            <div className="flex flex-1 flex-col gap-4 p-4 pt-0 mt-4">
                <div className="mx-4">
                    <div className="mb-4">
                        <Link href={route('schedules.index')}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="h-4 w-4 mr-2"/>
                                Back to Schedules
                            </Button>
                        </Link>
                    </div>

                    <div className="max-w-4xl mx-auto">
                        <div className="mb-6">
                            <h1 className="text-2xl font-bold">Create Schedule</h1>
                            <p className="text-muted-foreground">
                                Set up a new automated backup schedule
                            </p>
                        </div>
                        <ScheduleForm
                            dataSources={dataSources}
                            onSubmit={handleSubmit}
                        />
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
