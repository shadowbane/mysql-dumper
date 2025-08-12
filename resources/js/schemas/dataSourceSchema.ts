import { z } from 'zod';

export const dataSourceSchema = z.object({
    name: z.string()
        .min(1, 'Name is required')
        .max(255, 'Name must be 255 characters or less'),
    
    host: z.string()
        .min(1, 'Host is required')
        .max(255, 'Host must be 255 characters or less'),
    
    port: z.number()
        .int('Port must be an integer')
        .min(1, 'Port must be greater than 0')
        .max(65535, 'Port must be less than 65536'),
    
    database: z.string()
        .min(1, 'Database name is required')
        .max(255, 'Database name must be 255 characters or less'),
    
    username: z.string()
        .min(1, 'Username is required')
        .max(255, 'Username must be 255 characters or less'),
    
    password: z.string()
        .min(1, 'Password is required')
        .max(255, 'Password must be 255 characters or less'),
    
    is_active: z.boolean(),
    
    skipped_tables: z.string()
        .optional()
        .refine((value) => {
            if (!value || value.trim() === '') return true;
            // Validate comma-separated table names (basic validation)
            const tables = value.split(',').map(t => t.trim());
            return tables.every(table => /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(table));
        }, 'Invalid table names. Use only letters, numbers, and underscores, separated by commas'),
    
    structure_only: z.string()
        .optional()
        .refine((value) => {
            if (!value || value.trim() === '') return true;
            // Validate comma-separated table names (basic validation)
            const tables = value.split(',').map(t => t.trim());
            return tables.every(table => /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(table));
        }, 'Invalid table names. Use only letters, numbers, and underscores, separated by commas'),
});

export type DataSourceFormData = z.infer<typeof dataSourceSchema>;

// Schema for editing (password optional)
export const dataSourceEditSchema = dataSourceSchema.extend({
    password: z.string().optional(),
});

export type DataSourceEditFormData = z.infer<typeof dataSourceEditSchema>;