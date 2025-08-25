import {PropsWithChildren} from 'react';
import {Toaster} from '@/components/ui/sonner';
import Header from '@/components/header';
import {cn} from "@/lib/utils";

export default function MainLayout({children}: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col">
            <Header/>
            {/*<main className="flex-1">{children}</main>*/}
            <main
                data-slot="sidebar-inset"
                className={cn(
                    "bg-background relative flex w-full flex-1 flex-col",
                    "md:peer-data-[variant=inset]:m-2 md:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:rounded-xl md:peer-data-[variant=inset]:shadow-sm md:peer-data-[variant=inset]:peer-data-[state=collapsed]:ml-2",
                )}
            >
                <div className="relative h-full">
                    {children}
                </div>
            </main>
            <Toaster
                expand={true}
                position="bottom-right"
                toastOptions={{
                    classNames: {
                        description: '!text-gray-700 !dark:text-gray-300',
                    },
                }}
            />
        </div>
    );
}
