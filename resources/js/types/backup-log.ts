import { BaseModel } from "@/types/base-model";

export interface BackupLog extends BaseModel {
    data_source_id: string;
    status: 'Pending' | 'Running' | 'Completed' | 'Failed';
    type: 'Automated' | 'Manual';
    disk: string | null;
    filename: string | null;
    file_path: string | null;
    file_size: number | null;
    warnings: string[] | null;
    errors: string[] | null;
    metadata: Record<string, any> | null;
    started_at: string | null;
    completed_at: string | null;
}
