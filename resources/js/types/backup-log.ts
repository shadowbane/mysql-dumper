import { BaseModel } from "@/types/base-model";
import { BackupLogTimeline } from './backup-log-timeline';
import { DataSource } from './datasource';
import { File } from './file';

export interface BackupLog extends BaseModel {
    data_source_id: string;
    schedule_id: string | null;
    status: 'Pending' | 'Running' | 'Completed' | 'Failed' | 'Backup Ready' | 'Storing to Destinations' | 'Partially Failed';
    type: 'Automated' | 'Manual';
    warnings: string[] | null;
    errors: string[] | null;
    metadata: Record<string, any> | null;
    locked: boolean;
    started_at: string | null;
    completed_at: string | null;

    // Computed attributes
    human_size?: string; // e.g., "2.5 MB"
    human_duration?: string; // e.g., "2m 30s"
    is_file_available?: boolean;

    // Relationships
    data_source?: DataSource;
    timelines?: BackupLogTimeline[];
    files?: File[];
}
