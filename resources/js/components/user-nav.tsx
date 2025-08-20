import { Link, usePage } from '@inertiajs/react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PageProps } from '@/types';

export function UserNav() {
    const { auth } = usePage<PageProps>().props;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="relative h-8 w-8 rounded-full">
                    <Avatar className="h-8 w-8">
                        <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                        <AvatarFallback>{auth.user.name[0]}</AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end" forceMount>
                <DropdownMenuLabel className="font-normal">
                    <div className="flex flex-col space-y-1">
                        <p className="text-sm font-medium leading-none">{auth.user.name}</p>
                        <p className="text-xs leading-none text-muted-foreground">{auth.user.email}</p>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    {/*<DropdownMenuItem asChild>*/}
                    {/*    <Link*/}
                    {/*        // href={route('profile.edit')}*/}
                    {/*        href="#"*/}
                    {/*    >Profile</Link>*/}
                    {/*</DropdownMenuItem>*/}
                    {/*<DropdownMenuItem asChild>*/}
                    {/*    <Link*/}
                    {/*        // href={route('settings')}*/}
                    {/*        href="#"*/}
                    {/*    >Settings</Link>*/}
                    {/*</DropdownMenuItem>*/}
                    <DropdownMenuItem asChild>
                        <Link
                            href={route('logout')}
                        >
                            Log out
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
