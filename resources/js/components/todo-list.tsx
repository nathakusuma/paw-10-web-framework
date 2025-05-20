import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Todo } from '@/types/todo';
import { PlusCircle } from 'lucide-react';
import { Link } from '@inertiajs/react';
import TodoItem from './todo-item';
import { useState } from 'react';

interface TodoListProps {
    todos: Todo[];
}

export default function TodoList({ todos }: TodoListProps) {
    const [filteredStatus, setFilteredStatus] = useState<'all' | 'active' | 'completed'>('all');

    const filteredTodos = todos.filter(todo => {
        if (filteredStatus === 'all') return true;
        if (filteredStatus === 'active') return !todo.is_completed;
        if (filteredStatus === 'completed') return todo.is_completed;
        return true;
    });

    const activeTodoCount = todos.filter(todo => !todo.is_completed).length;
    const completedTodoCount = todos.filter(todo => todo.is_completed).length;

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
                        variant={filteredStatus === 'all' ? 'default' : 'ghost'}
                        onClick={() => setFilteredStatus('all')}
                    >
                        All
                    </Button>
                    <Button
                        size="sm"
                        variant={filteredStatus === 'active' ? 'default' : 'ghost'}
                        onClick={() => setFilteredStatus('active')}
                    >
                        Active
                    </Button>
                    <Button
                        size="sm"
                        variant={filteredStatus === 'completed' ? 'default' : 'ghost'}
                        onClick={() => setFilteredStatus('completed')}
                    >
                        Completed
                    </Button>
                </div>
                <Button asChild>
                    <Link href={route('todos.create')}>
                        <PlusCircle className="h-4 w-4 mr-2" />
                        Add Task
                    </Link>
                </Button>
            </div>

            <Card>
                <CardContent className="p-0">
                    {filteredTodos.length === 0 ? (
                        <div className="p-6 text-center text-muted-foreground">
                            {filteredStatus === 'all'
                                ? "You don't have any tasks yet. Create one!"
                                : filteredStatus === 'active'
                                    ? "You don't have any active tasks."
                                    : "You don't have any completed tasks."}
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {filteredTodos.map((todo) => (
                                <TodoItem key={todo.id} todo={todo} />
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
