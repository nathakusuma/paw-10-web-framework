import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import { Todo } from '@/types/todo';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';

interface EditTodoModalProps {
    todo: Todo | null;
    isOpen: boolean;
    onClose: () => void;
}

export default function EditTodoModal({ todo, isOpen, onClose }: EditTodoModalProps) {
    const { data, setData, put, processing, errors, reset } = useForm({
        title: todo?.title || '',
        description: todo?.description || '',
        is_completed: todo?.is_completed || false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!todo) return;

        put(route('todos.update', todo.id), {
            onSuccess: () => {
                onClose();
            },
        });
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Task</DialogTitle>
                </DialogHeader>
                {todo && (
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Task title"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description (optional)</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Describe your task..."
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_completed"
                                    checked={data.is_completed}
                                    onCheckedChange={(checked) =>
                                        setData('is_completed', checked === true)
                                    }
                                />
                                <Label htmlFor="is_completed">Mark as completed</Label>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Update Task
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
