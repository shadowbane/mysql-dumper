import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Calendar, Clock, Database } from 'lucide-react';
import { Schedule } from '@/types/schedule';
import { DataSource } from '@/types/datasource';
import { scheduleSchema, ScheduleFormData } from '@/schemas/scheduleSchema';

interface ScheduleFormProps {
    schedule?: Schedule;
    dataSources: DataSource[];
    onSubmit: (data: ScheduleFormData) => void;
    isEditing?: boolean;
}

const daysOfWeek = [
    { id: 1, label: 'Monday' },
    { id: 2, label: 'Tuesday' },
    { id: 3, label: 'Wednesday' },
    { id: 4, label: 'Thursday' },
    { id: 5, label: 'Friday' },
    { id: 6, label: 'Saturday' },
    { id: 7, label: 'Sunday' },
];

const hours = Array.from({ length: 24 }, (_, i) => ({
    value: i,
    label: `${i.toString().padStart(2, '0')}:00`,
}));

export default function ScheduleForm({ schedule, dataSources, onSubmit, isEditing = false }: ScheduleFormProps) {
    const form = useForm<ScheduleFormData>({
        resolver: zodResolver(scheduleSchema),
        defaultValues: {
            name: schedule?.name || '',
            description: schedule?.description || '',
            hour: schedule?.hour || 0,
            days_of_week: schedule?.days_of_week || [],
            data_source_ids: schedule?.data_sources?.map(ds => ds.id!) || [],
            is_active: schedule?.is_active ?? true,
        },
    });

    const handleSubmit = (data: ScheduleFormData) => {
        onSubmit(data);
    };

    return (
        <Form {...form}>
            <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5" />
                            Schedule Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FormField
                            control={form.control}
                            name="name"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Schedule Name</FormLabel>
                                    <FormControl>
                                        <Input 
                                            placeholder="e.g., Daily Backup, Weekly Archive" 
                                            {...field} 
                                        />
                                    </FormControl>
                                    <FormDescription>
                                        A descriptive name for this backup schedule
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="description"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Description (Optional)</FormLabel>
                                    <FormControl>
                                        <Textarea 
                                            placeholder="Optional description of this backup schedule..."
                                            rows={3}
                                            {...field} 
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="is_active"
                            render={({ field }) => (
                                <FormItem className="flex flex-row items-center justify-between rounded-lg border p-4">
                                    <div className="space-y-0.5">
                                        <FormLabel className="text-base">
                                            Active Schedule
                                        </FormLabel>
                                        <FormDescription>
                                            Enable this schedule to run automatic backups
                                        </FormDescription>
                                    </div>
                                    <FormControl>
                                        <Switch
                                            checked={field.value}
                                            onCheckedChange={field.onChange}
                                        />
                                    </FormControl>
                                </FormItem>
                            )}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-5 w-5" />
                            Schedule Timing
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FormField
                            control={form.control}
                            name="hour"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Time (UTC)</FormLabel>
                                    <Select 
                                        onValueChange={(value) => field.onChange(parseInt(value))} 
                                        defaultValue={field.value.toString()}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select time" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            {hours.map((hour) => (
                                                <SelectItem key={hour.value} value={hour.value.toString()}>
                                                    {hour.label} UTC
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FormDescription>
                                        Time is stored and runs in UTC. Convert your local time to UTC.
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="days_of_week"
                            render={() => (
                                <FormItem>
                                    <div className="mb-4">
                                        <FormLabel className="text-base">Days of Week</FormLabel>
                                        <FormDescription>
                                            Select which days this schedule should run
                                        </FormDescription>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        {daysOfWeek.map((day) => (
                                            <FormField
                                                key={day.id}
                                                control={form.control}
                                                name="days_of_week"
                                                render={({ field }) => {
                                                    return (
                                                        <FormItem
                                                            key={day.id}
                                                            className="flex flex-row items-start space-x-3 space-y-0"
                                                        >
                                                            <FormControl>
                                                                <Checkbox
                                                                    checked={field.value?.includes(day.id)}
                                                                    onCheckedChange={(checked) => {
                                                                        return checked
                                                                            ? field.onChange([...field.value, day.id])
                                                                            : field.onChange(
                                                                                field.value?.filter(
                                                                                    (value) => value !== day.id
                                                                                )
                                                                            )
                                                                    }}
                                                                />
                                                            </FormControl>
                                                            <FormLabel className="text-sm font-normal">
                                                                {day.label}
                                                            </FormLabel>
                                                        </FormItem>
                                                    )
                                                }}
                                            />
                                        ))}
                                    </div>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Database className="h-5 w-5" />
                            Data Sources
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FormField
                            control={form.control}
                            name="data_source_ids"
                            render={() => (
                                <FormItem>
                                    <div className="mb-4">
                                        <FormLabel className="text-base">Select Data Sources</FormLabel>
                                        <FormDescription>
                                            Choose which databases to backup on this schedule
                                        </FormDescription>
                                    </div>
                                    <div className="space-y-3">
                                        {dataSources.map((dataSource) => (
                                            <FormField
                                                key={dataSource.id}
                                                control={form.control}
                                                name="data_source_ids"
                                                render={({ field }) => {
                                                    return (
                                                        <FormItem
                                                            key={dataSource.id}
                                                            className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4"
                                                        >
                                                            <FormControl>
                                                                <Checkbox
                                                                    checked={field.value?.includes(dataSource.id!)}
                                                                    onCheckedChange={(checked) => {
                                                                        return checked
                                                                            ? field.onChange([...field.value, dataSource.id!])
                                                                            : field.onChange(
                                                                                field.value?.filter(
                                                                                    (value) => value !== dataSource.id
                                                                                )
                                                                            )
                                                                    }}
                                                                />
                                                            </FormControl>
                                                            <div className="grid gap-1.5 leading-none">
                                                                <FormLabel className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                                                    {dataSource.name}
                                                                </FormLabel>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {dataSource.host}:{dataSource.port} / {dataSource.database}
                                                                </p>
                                                            </div>
                                                        </FormItem>
                                                    )
                                                }}
                                            />
                                        ))}
                                    </div>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </CardContent>
                </Card>

                <div className="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => window.history.back()}
                    >
                        Cancel
                    </Button>

                    <Button type="submit">
                        {isEditing ? 'Update Schedule' : 'Create Schedule'}
                    </Button>
                </div>
            </form>
        </Form>
    );
}