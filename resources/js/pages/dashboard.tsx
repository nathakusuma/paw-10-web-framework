import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Todo } from '@/types/todo';
import TodoList from '@/components/todo-list';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface Props {
    todos: Todo[];
}

export default function Dashboard({ todos }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">Your Tasks</h1>
                    <p className="text-muted-foreground">Manage your tasks and stay organized</p>
                </div>

                <TodoList todos={todos} />
            </div>
        </AppLayout>
    );
}
