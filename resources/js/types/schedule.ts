import { BaseModel } from "@/types/base-model";
import { DataSource } from './datasource';
import { BackupLog } from './backup-log';

export interface Schedule extends BaseModel {
    name: string;
    description: string | null;
    hour: number; // 0-23 (UTC)
    minute: number; // 0-59 (UTC)
    days_of_week: number[]; // [1,2,3,4,5] where 1=Monday, 7=Sunday
    is_active: boolean;
    last_run_at: string | null;
    
    // Computed attributes
    human_days?: string; // e.g., "Weekdays", "Every day", "Monday, Wednesday, Friday"
    human_time?: string; // e.g., "14:30 UTC"
    
    // Relationships
    data_sources?: DataSource[];
    backup_logs?: BackupLog[];
    
    // Computed counts (for list views)
    data_sources_count?: number;
    latest_backup_log?: BackupLog | null;
}