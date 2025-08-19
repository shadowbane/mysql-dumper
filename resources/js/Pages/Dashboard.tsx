import {Head, router} from '@inertiajs/react';
import {Activity, ArrowUpRight, CreditCard, Download, HardDrive, Users} from 'lucide-react';

import MainLayout from '@/layouts/Main';
import {Avatar, AvatarFallback, AvatarImage} from '@/components/ui/avatar';
import {Badge} from '@/components/ui/badge';
import {Button} from '@/components/ui/button';
import {Card, CardContent, CardDescription, CardHeader, CardTitle} from '@/components/ui/card';
import {Table, TableBody, TableCell, TableHead, TableHeader, TableRow} from '@/components/ui/table';
import {DataSource} from "@/types/datasource";
import { toast } from 'sonner';
import {triggerBackup} from "@/components/functions/backups";

export default function Dashboard({stats, recentBackups, activeDataSources}: App.Dashboard.PageProps) {
    console.log(recentBackups);
    console.log(activeDataSources);

    return (
        <MainLayout>
            <Head title="Dashboard"/>
            <div className="flex min-h-screen w-full flex-col">
                <main className="flex flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
                    <div className="grid gap-4 md:grid-cols-2 md:gap-8 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Data Sources</CardTitle>
                                <Users className="h-4 w-4 text-muted-foreground"/>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.totalDataSources.count}</div>
                                <p className="text-xs text-muted-foreground">
                                    {stats.totalDataSources.comparison > 0 ? '+' : ''}{stats.totalDataSources.comparison} since
                                    last week
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Storage Used</CardTitle>
                                <HardDrive className="h-4 w-4 text-muted-foreground"/>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.storageUsed.count}</div>
                                <p className="text-xs text-muted-foreground">
                                    {stats.storageUsed.comparison > 0 ? '+' : ''}{stats.storageUsed.comparison}% from
                                    last month
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Backups This Month</CardTitle>
                                <CreditCard className="h-4 w-4 text-muted-foreground"/>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.backupsThisMonth.count}</div>
                                <p className="text-xs text-muted-foreground">
                                    {stats.backupsThisMonth.comparison > 0 ? '+' : ''}{stats.backupsThisMonth.comparison.toFixed(1)}%
                                    from last month
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Recent Failures</CardTitle>
                                <Activity className="h-4 w-4 text-muted-foreground"/>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-destructive">{stats.recentFailures.count}</div>
                                <p className="text-xs text-muted-foreground">In the last 7 days</p>
                            </CardContent>
                        </Card>
                    </div>
                    <div className="grid gap-4 md:gap-8 lg:grid-cols-2 xl:grid-cols-3">
                        <Card className="xl:col-span-2">
                            <CardHeader className="flex flex-row items-center">
                                <div className="grid gap-2">
                                    <CardTitle>Recent Backups</CardTitle>
                                    <CardDescription>An overview of your most recent database backups.</CardDescription>
                                </div>
                                <Button asChild size="sm" className="ml-auto gap-1">
                                    <a href={route('backup-logs.index')}>
                                        View All
                                        <ArrowUpRight className="h-4 w-4"/>
                                    </a>
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Data Source</TableHead>
                                            <TableHead className="hidden xl:table-column">Status</TableHead>
                                            <TableHead className="hidden xl:table-column">Size</TableHead>
                                            <TableHead className="text-right">Date</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recentBackups.map((backup) => (
                                            <TableRow key={backup.id}>
                                                <TableCell>
                                                    <div className="font-medium">{backup.data_source_name}</div>
                                                    <div className="hidden text-sm text-muted-foreground md:inline">
                                                        ID: {backup.id}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="hidden xl:table-column">
                                                    <Badge
                                                        className="text-xs"
                                                        variant={
                                                            backup.status === 'completed' ? 'outline' :
                                                                backup.status === 'failed' ? 'destructive' :
                                                                    backup.status === 'running' ? 'secondary' : 'outline'
                                                        }
                                                    >
                                                        {backup.status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="hidden xl:table-column">{backup.size}</TableCell>
                                                <TableCell className="text-right">{backup.date}</TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        disabled={!backup.is_file_available}
                                                    >
                                                        <Download className="h-4 w-4"/>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Active Data Sources</CardTitle>
                                <CardDescription>
                                    An overview of your most recent database backups.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-8">
                                {activeDataSources.map((dataSource) => (
                                    <div key={dataSource.id} className="flex items-center gap-4">
                                        <Avatar className="hidden h-9 w-9 sm:flex">
                                            <AvatarFallback>
                                                {dataSource.name.substring(0, 2).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="grid gap-1">
                                            <p className="text-sm font-medium leading-none">{dataSource.name}</p>
                                            <p className="text-sm text-muted-foreground">{dataSource.host}</p>
                                        </div>
                                        <div className="ml-auto font-medium">
                                            <Button
                                                size="sm"
                                                onClick={() => triggerBackup(dataSource)}
                                                disabled={["Pending", "Running"].includes(dataSource?.latest_backup_log?.status)}
                                            >Backup Now</Button>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </main>
            </div>
        </MainLayout>
    );
}
