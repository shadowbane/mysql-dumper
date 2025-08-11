
import { PropsWithChildren } from 'react';

interface AuthLayoutProps {
    title: string;
    description: string;
}

export default function AuthLayout({ title, description, children }: PropsWithChildren<AuthLayoutProps>) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-100 dark:bg-gray-950">
            <div className="w-full max-w-md space-y-8 p-4">
                <div className="text-center">
                    {/* You can replace this with your actual logo */}
                    <div className="mx-auto mb-4 h-12 w-12 rounded-full bg-gray-300 dark:bg-gray-700" />
                    <h1 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-50">{title}</h1>
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{description}</p>
                </div>
                <div className="rounded-lg border bg-white p-8 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    {children}
                </div>
            </div>
        </div>
    );
}
