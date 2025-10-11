export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string|null;
    is_administrator?: boolean;
}

export interface Role {
    id?: number;
    name: string;
    slug: string;
    description?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface DataSourceUser {
    id: number;
    data_source_id: string;
    user_id: number;
    created_at: string;
    updated_at: string;
}

export interface RoleUser {
    id: number;
    user_id: number;
    role_id: number;
    created_at?: string;
    updated_at?: string;
}
