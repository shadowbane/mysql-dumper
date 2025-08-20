import { BaseModel } from "@/types/base-model";

export interface File extends BaseModel {
    uuid: string;
    filename: string;
    path: string;
    disk: string;
    label?: string;
    mime_type?: string;
    size_bytes: number;
    fileable_type: string;
    fileable_id: string;
    is_public: boolean;
    ordering?: number;
    hash?: string;
    deleted_at?: string;
    
    // Computed attributes
    url?: string;
    human_size?: string;
}