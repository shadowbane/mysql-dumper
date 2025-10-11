import { z } from 'zod';

export const profileSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    password: z.string().min(8, 'Password must be at least 8 characters').optional().or(z.literal('')),
    password_confirmation: z.string().optional().or(z.literal('')),
}).refine((data) => {
    // Only validate password confirmation if password is provided
    if (data.password && data.password.length > 0) {
        return data.password === data.password_confirmation;
    }
    return true;
}, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
});

export type ProfileFormData = z.infer<typeof profileSchema>;