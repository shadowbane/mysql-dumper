import { BaseModel } from "@/types/base-model";
import { BackupLogTimeline } from './backup-log-timeline';
import { DataSource } from './datasource';

export interface BackupLog extends BaseModel {
    data_source_id: string;
    schedule_id: string | null;
    status: 'Pending' | 'Running' | 'Completed' | 'Failed';
    type: 'Automated' | 'Manual';
    disk: string | null;
    filename: string | null;
    file_path: string | null;
    file_size: number | null;
    file_deleted_at: string | null;
    warnings: string[] | null;
    errors: string[] | null;
    metadata: Record<string, any> | null;
    started_at: string | null;
    completed_at: string | null;
    
    // Computed attributes
    human_size?: string; // e.g., "2.5 MB"
    human_duration?: string; // e.g., "2m 30s"
    is_file_available?: boolean;
    
    // Relationships
    data_source?: DataSource;
    timelines?: BackupLogTimeline[];
}
