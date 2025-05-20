import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { PlusCircle, ChevronLeft, ChevronRight } from 'lucide-react';
import TodoItem from './todo-item';
import { useState } from 'react';
import CreateTodoModal from './create-todo-modal';
import EditTodoModal from './edit-todo-modal';
import { router, Link } from '@inertiajs/react';

interface Todo {
    id: number;
    title: string;
    description: string | null;
    is_completed: boolean;
    created_at: string;
    updated_at: string;
}

interface PaginatedData {
    current_page: number;
    data: Todo[];
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

interface TodoListProps {
    todos: PaginatedData;
    filter: string;
}

export default function TodoList({ todos, filter }: TodoListProps) {
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingTodo, setEditingTodo] = useState<Todo | null>(null);

    const activeTodoCount = todos.total - todos.data.filter(todo => todo.is_completed).length;
    const completedTodoCount = todos.data.filter(todo => todo.is_completed).length;

    const handleFilterChange = (newFilter: string) => {
        router.get(route('dashboard'), {
            filter: newFilter
        }, {
            preserveState: true,
            only: ['todos', 'filter']
        });
    };

    const handleEditTodo = (todo: Todo) => {
        setEditingTodo(todo);
    };

    const handleCloseEditModal = () => {
        setEditingTodo(null);
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <div className="text-muted-foreground text-sm">
                    <span className="mr-2">
                        {activeTodoCount} active
                    </span>
                    <span>
                        {completedTodoCount} completed
                    </span>
                </div>
                <div className="flex gap-2">
                    <Button
                        size="sm"
                        variant={filter === 'all' ? 'default' : 'ghost'}
                        onClick={() => handleFilterChange('all')}
                    >
                        All
                    </Button>
                    <Button
                        size="sm"
                        variant={filter === 'active' ? 'default' : 'ghost'}
                        onClick={() => handleFilterChange('active')}
                    >
                        Active
                    </Button>
                    <Button
                        size="sm"
                        variant={filter === 'completed' ? 'default' : 'ghost'}
                        onClick={() => handleFilterChange('completed')}
                    >
                        Completed
                    </Button>
                </div>
                <Button onClick={() => setIsCreateModalOpen(true)}>
                    <PlusCircle className="h-4 w-4 mr-2" />
                    Add Task
                </Button>
            </div>

            <Card>
                <CardContent className="p-0">
                    {todos.data.length === 0 ? (
                        <div className="p-6 text-center text-muted-foreground">
                            {filter === 'all'
                                ? "You don't have any tasks yet. Create one!"
                                : filter === 'active'
                                    ? "You don't have any active tasks."
                                    : "You don't have any completed tasks."}
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {todos.data.map((todo) => (
                                <TodoItem
                                    key={todo.id}
                                    todo={todo}
                                    onEdit={handleEditTodo}
                                    filter={filter}
                                />
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            {/* Pagination */}
            {todos.last_page > 1 && (
                <div className="flex items-center justify-center gap-2 py-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.get(todos.prev_page_url || '')}
                        disabled={!todos.prev_page_url}
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </Button>

                    <span className="text-sm">
                        Page {todos.current_page} of {todos.last_page}
                    </span>

                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.get(todos.next_page_url || '')}
                        disabled={!todos.next_page_url}
                    >
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
            )}

            {/* Modals */}
            <CreateTodoModal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                filter={filter}
            />

            <EditTodoModal
                todo={editingTodo}
                isOpen={Boolean(editingTodo)}
                onClose={handleCloseEditModal}
                filter={filter}
            />
        </div>
    );
}
