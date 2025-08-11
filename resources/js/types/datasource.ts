import {BaseModel} from "@/types/base-model";

export interface DataSource extends BaseModel {
    name: string;
    host: string;
    port: number;
    database: string;
    username: string;
    is_active: boolean;
    skipped_tables: string | null;
}
