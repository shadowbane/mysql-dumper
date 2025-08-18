import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {applicationLinks} from "@/types/applicationLinks";

export function MainNav({ className, ...props }: React.HTMLAttributes<HTMLElement>) {
    const { url } = usePage();

    return (
        <NavigationMenu className={cn('flex-col items-start md:flex-row md:items-center', className)} {...props}>
            <NavigationMenuList className="flex-col space-y-2 md:flex-row md:space-x-4 md:space-y-0">
                {applicationLinks.map((link) => (
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
