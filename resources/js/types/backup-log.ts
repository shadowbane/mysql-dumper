import { BaseModel } from "@/types/base-model";
import { BackupLogTimeline } from './backup-log-timeline';

export interface BackupLog extends BaseModel {
    data_source_id: string;
    status: 'Pending' | 'Running' | 'Completed' | 'Failed';
    type: 'Automated' | 'Manual';
    disk: string | null;
    filename: string | null;
    file_path: string | null;
    file_size: number | null;
    human_size?: string; // e.g., "2.5 MB"
    warnings: string[] | null;
    errors: string[] | null;
    metadata: Record<string, any> | null;
    started_at: string | null;
    completed_at: string | null;
    
    // Relationships
    timelines?: BackupLogTimeline[];
}
