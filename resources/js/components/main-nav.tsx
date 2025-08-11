import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';

export function MainNav({ className, ...props }: React.HTMLAttributes<HTMLElement>) {
    const { url } = usePage();

    const links = [
        { href: route('dashboard'), label: 'Dashboard' },
        { href: route('data-sources'), label: 'Data Sources' },
        { href: route('schedules'), label: 'Schedules' },
        { href: route('logs'), label: 'Backup Logs' },
    ];

    return (
        <NavigationMenu className={cn('flex-col items-start md:flex-row md:items-center', className)} {...props}>
            <NavigationMenuList className="flex-col space-y-2 md:flex-row md:space-x-4 md:space-y-0">
                {links.map((link) => (
                    <NavigationMenuItem key={link.href}>
                        <Link
                            href={link.href}
                        >
                            {link.label}
                        </Link>
                    </NavigationMenuItem>
                ))}
            </NavigationMenuList>
        </NavigationMenu>
    );
}
