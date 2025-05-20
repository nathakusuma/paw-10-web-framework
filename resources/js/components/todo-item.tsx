import { Todo } from '@/types/todo';
import { Checkbox } from '@/components/ui/checkbox';
import { router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useState } from 'react';

interface TodoItemProps {
    todo: Todo;
    onEdit: (todo: Todo) => void;
    filter: string;
}

export default function TodoItem({ todo, onEdit, filter }: TodoItemProps) {
    const [isDeleting, setIsDeleting] = useState(false);
    const [isUpdating, setIsUpdating] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);

    const handleToggleComplete = () => {
        setIsUpdating(true);
        router.put(route('todos.update', todo.id), {
            title: todo.title,
            description: todo.description,
            is_completed: !todo.is_completed,
            filter: filter
        }, {
            preserveScroll: true,
            onFinish: () => setIsUpdating(false)
        });
    };

    const handleDelete = () => {
        setIsDeleting(true);
        router.delete(route('todos.destroy', todo.id), {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
                setShowDeleteDialog(false);
            }
        });
    };

    return (
        <li className="flex items-center justify-between p-4 group">
            <div className="flex items-start gap-3 flex-1">
                <Checkbox
                    id={`todo-${todo.id}`}
                    checked={todo.is_completed}
                    onCheckedChange={handleToggleComplete}
                    disabled={isUpdating}
                    className="mt-1"
                />
                <div className="flex-1">
                    <label
                        htmlFor={`todo-${todo.id}`}
                        className={`font-medium cursor-pointer ${todo.is_completed ? 'line-through text-muted-foreground' : ''}`}
                    >
                        {todo.title}
                    </label>
                    {todo.description && (
                        <p className={`text-sm text-muted-foreground mt-1 ${todo.is_completed ? 'line-through' : ''}`}>
                            {todo.description}
                        </p>
                    )}
                </div>
            </div>
            <div className="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => onEdit(todo)}
                >
                    <Pencil className="h-4 w-4" />
                    <span className="sr-only">Edit</span>
                </Button>

                <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                    <DialogTrigger asChild>
                        <Button variant="ghost" size="icon">
                            <Trash2 className="h-4 w-4 text-destructive" />
                            <span className="sr-only">Delete</span>
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Are you sure?</DialogTitle>
                            <DialogDescription>
                                This will permanently delete the task "{todo.title}".
                                This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setShowDeleteDialog(false)}
                                className="mt-2 sm:mt-0"
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                                disabled={isDeleting}
                            >
                                {isDeleting ? 'Deleting...' : 'Delete'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </li>
    );
}
