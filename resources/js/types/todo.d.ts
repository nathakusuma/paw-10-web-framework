export interface Todo {
    id: number;
    title: string;
    description: string | null;
    completed: boolean;
    created_at: string;
    updated_at: string;
}
