import { z } from 'zod';

export const scheduleSchema = z.object({
    name: z.string()
        .min(1, 'Name is required')
        .max(255, 'Name must be 255 characters or less'),
    
    description: z.string()
        .max(1000, 'Description must be 1000 characters or less')
        .optional(),
    
    hour: z.number()
        .int('Hour must be an integer')
        .min(0, 'Hour must be between 0 and 23')
        .max(23, 'Hour must be between 0 and 23'),
    
    days_of_week: z.array(z.number().int().min(1).max(7))
        .min(1, 'At least one day must be selected')
        .refine((days) => {
            // Ensure no duplicates
            return new Set(days).size === days.length;
        }, 'Duplicate days are not allowed'),
    
    data_source_ids: z.array(z.string())
        .min(1, 'At least one data source must be selected'),
    
    is_active: z.boolean(),
});

export type ScheduleFormData = z.infer<typeof scheduleSchema>;