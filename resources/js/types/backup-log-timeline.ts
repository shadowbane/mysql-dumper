export interface BackupLogTimeline {
    id: number;
    backup_log_id: number;
    status: 'Pending' | 'Running' | 'Completed' | 'Failed';
    metadata: Record<string, any>;
    created_at: string;
    updated_at: string;
    duration_from_previous?: number; // in seconds
    human_duration_from_previous?: string; // e.g., "2m", "1.5h"
}