import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';

interface CreateTodoModalProps {
    isOpen: boolean;
    onClose: () => void;
    filter: string;
}

export default function CreateTodoModal({ isOpen, onClose, filter }: CreateTodoModalProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        description: '',
        filter: filter,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('todos.store'), {
            onSuccess: () => {
                reset();
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
                    <DialogTitle>Create New Task</DialogTitle>
                </DialogHeader>
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
                                value={data.description || ''}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Describe your task..."
                                rows={3}
                            />
                            <InputError message={errors.description} />
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
                            Create Task
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
