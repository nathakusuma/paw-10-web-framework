import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import TodoList from '@/components/todo-list';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface PaginatedTodos {
    current_page: number;
    data: Array<{
        id: number;
        title: string;
        description: string | null;
        is_completed: boolean;
        created_at: string;
        updated_at: string;
    }>;
    first_page_url: string;
    from: number;
    last_page: number;
    last_page_url: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
}

interface Props {
    todos: PaginatedTodos;
    filter: string;
}

export default function Dashboard({ todos, filter }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">Your Tasks</h1>
                    <p className="text-muted-foreground">Manage your tasks and stay organized</p>
                </div>

                <TodoList todos={todos} filter={filter} />
            </div>
        </AppLayout>
    );
}
