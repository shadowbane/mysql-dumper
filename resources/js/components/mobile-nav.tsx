import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';

export function MobileNav() {
    const { url } = usePage();

    const links = [
        { href: route('dashboard'), label: 'Dashboard', pattern: '/dashboard' },
        { href: route('data-sources.index'), label: 'Data Sources', pattern: '/data-sources' },
        { href: route('schedules'), label: 'Schedules', pattern: '/schedules' },
        { href: route('backup-logs.index'), label: 'Backup Logs', pattern: '/backup-logs' },
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
                            'text-foreground': url.startsWith(link.pattern),
                        },
                    )}
                >
                    {link.label}
                </Link>
            ))}
        </nav>
    );
}
