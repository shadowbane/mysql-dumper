import React from 'react';
import {Head, router} from '@inertiajs/react';
import {ArrowLeft} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Link} from '@inertiajs/react';
import MainLayout from '@/layouts/Main';
import ScheduleForm from "@/components/Custom/schedules/ScheduleForm";
import {Schedule} from '@/types/schedule';
import {DataSource} from '@/types/datasource';
import {ScheduleFormData} from '@/schemas/scheduleSchema';
import {toast} from 'sonner';
import {route} from 'ziggy-js';

interface Props {
    schedule: Schedule;
    dataSources: DataSource[];
}

export default function EditSchedule({schedule, dataSources}: Props) {
    const handleSubmit = (data: ScheduleFormData) => {
        router.put(route('schedules.update', {schedule: schedule.id}), data, {
            onSuccess: () => {
                toast.success('Schedule updated successfully', {
                    description: 'The backup schedule has been updated.',
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
            <Head title={`Edit Schedule - ${schedule.name}`}/>

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
                            <h1 className="text-2xl font-bold">Edit Schedule</h1>
                            <p className="text-muted-foreground">
                                Modify the backup schedule "{schedule.name}"
                            </p>
                        </div>
                        <ScheduleForm
                            schedule={schedule}
                            dataSources={dataSources}
                            onSubmit={handleSubmit}
                            isEditing={true}
                        />
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
