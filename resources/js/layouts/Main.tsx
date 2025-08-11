import { PropsWithChildren } from 'react';
import { Toaster } from '@/components/ui/sonner';
import Header from '@/components/header';

export default function MainLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col">
            <Header />
            <main className="flex-1">{children}</main>
            <Toaster />
        </div>
    );
}
