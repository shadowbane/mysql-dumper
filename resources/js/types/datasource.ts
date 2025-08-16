import {BaseModel} from "@/types/base-model";
import {BackupLog} from "@/types/backup-log";

export interface DataSource extends BaseModel {
    name: string;
    host: string;
    port: number;
    database: string;
    username: string;
    password: string | null;
    is_active: boolean;
    skipped_tables: string | null;
    structure_only: string | null;
    latest_backup_log?: BackupLog | null;
    is_backup_healthy?: boolean;
}
