import {DataSource} from "@/types/datasource";
import {BackupLog} from "@/types/backup-log";

declare namespace App.Dashboard {
    export interface Stats {
        totalDataSources: {
            count: number;
            comparison: number;
        };
        storageUsed: {
            count: string;
            comparison: number;
        };
        backupsThisMonth: {
            count: number;
            comparison: number;
        };
        recentFailures: {
            count: number;
        };
    }

    export interface PageProps extends App.Shared.PageProps {
        stats: Stats;
        recentBackups: Partial<BackupLog>
        activeDataSources: Partial<DataSource>[];
    }
}
