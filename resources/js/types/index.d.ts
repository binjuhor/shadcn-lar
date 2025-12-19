export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    roles?: Role[];
    role_names?: string[];
    permissions?: string[];
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions?: Permission[];
    permissions_count?: number;
    users_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    resource?: string;
    action?: string;
    created_at?: string;
    updated_at?: string;
}

export interface GroupedPermissions {
    [resource: string]: {
        name: string;
        action: string;
    }[];
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        permissions: string[];
        roles: string[];
    };
    flash: {
        success?: string;
        error?: string;
    };
};
