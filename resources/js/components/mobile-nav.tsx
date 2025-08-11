import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';

export function MobileNav() {
    const { url } = usePage();

    const links = [
        { href: route('dashboard'), label: 'Dashboard' },
        { href: route('data-sources.index'), label: 'Data Sources' },
        { href: route('schedules'), label: 'Schedules' },
        { href: route('logs'), label: 'Backup Logs' },
    ];

    return (
        <nav className="grid gap-2 text-lg font-medium">
            {links.map((link) => (
                <Link
                    key={link.href}
                    href={link.href}
                    className={cn(
                        'flex items-center gap-4 px-2.5 text-muted-foreground hover:text-foreground',
                        {
                            'text-foreground': url.startsWith(link.href),
                        },
                    )}
                >
                    {link.label}
                </Link>
            ))}
        </nav>
    );
}
