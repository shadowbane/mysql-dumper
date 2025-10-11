import { z } from 'zod';

export const userSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    email: z.string().email('Invalid email address').max(255),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string(),
    roles: z.array(z.number()).min(1, 'At least one role must be selected'),
    data_sources: z.array(z.string()).optional(),
}).refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
});

export const userEditSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    email: z.string().email('Invalid email address').max(255),
    password: z.string().min(8, 'Password must be at least 8 characters').optional().or(z.literal('')),
    password_confirmation: z.string().optional().or(z.literal('')),
    roles: z.array(z.number()).min(1, 'At least one role must be selected'),
    data_sources: z.array(z.string()).optional(),
}).refine((data) => {
    if (data.password && data.password.length > 0) {
        return data.password === data.password_confirmation;
    }
    return true;
}, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
});

export type UserFormData = z.infer<typeof userSchema>;
export type UserEditFormData = z.infer<typeof userEditSchema>;