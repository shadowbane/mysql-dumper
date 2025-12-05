import { Link, usePage } from '@inertiajs/react';
import { Menu, Search } from 'lucide-react';

import { MainNav } from '@/components/main-nav';
import { MobileNav } from '@/components/mobile-nav';
import { UserNav } from '@/components/user-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';

export default function Header() {
    const {props} = usePage();
    const user = props?.auth?.user;

    if (user && window.rybbit) {
        window.rybbit.identify(user.email, {
            user
        });
    }

    return (
        <header className="sticky top-0 z-50 w-full border-b bg-white shadow-sm">
            <div className="container flex h-16 max-w-screen-2xl items-center justify-between px-4">
                <div className="flex items-center gap-6">
                    {/* Mobile Navigation Trigger */}
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button variant="outline" size="icon" className="shrink-0 md:hidden">
                                <Menu className="h-5 w-5" />
                                <span className="sr-only">Toggle navigation menu</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" className="flex flex-col">
                            <Link href={route('dashboard')} className="mb-1 flex items-center space-x-2 mt-4 ml-2">
                                <span className="font-bold">{props.appName}</span>
                            </Link>
                            <hr/>
                            <MobileNav />
                        </SheetContent>
                    </Sheet>

                    {/* Desktop Logo and Navigation */}
                    <Link href={route('dashboard')} className="mr-6 hidden items-center space-x-2 md:flex">
                        {/*<div className="h-8 w-8 rounded-full bg-primary" />*/}
                        <span className="hidden font-bold sm:inline-block">{props.appName}</span>
                    </Link>
                    <MainNav className="hidden md:flex" />
                </div>

                <div className="flex items-center gap-4">
                    <UserNav />
                </div>
            </div>
        </header>
    );
}

