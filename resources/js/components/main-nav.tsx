import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';

export function MainNav({ className, ...props }: React.HTMLAttributes<HTMLElement>) {
    const { url } = usePage();

    const links = [
        { href: route('dashboard'), label: 'Dashboard', pattern: '/dashboard' },
        { href: route('data-sources.index'), label: 'Data Sources', pattern: '/data-sources' },
        { href: route('schedules'), label: 'Schedules', pattern: '/schedules' },
        { href: route('backup-logs.index'), label: 'Backup Logs', pattern: '/backup-logs' },
    ];

    return (
        <NavigationMenu className={cn('flex-col items-start md:flex-row md:items-center', className)} {...props}>
            <NavigationMenuList className="flex-col space-y-2 md:flex-row md:space-x-4 md:space-y-0">
                {links.map((link) => (
                    <NavigationMenuItem key={link.href}>
                        <Link
                            href={link.href}
                            className={cn(
                                navigationMenuTriggerStyle(),
                                {
                                    'bg-accent text-accent-foreground': url.startsWith(link.pattern),
                                },
                            )}
                        >
                            {link.label}
                        </Link>
                    </NavigationMenuItem>
                ))}
            </NavigationMenuList>
        </NavigationMenu>
    );
}
