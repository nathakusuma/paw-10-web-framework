import EditTodo from '@/components/edit-todo';
import { Todo } from '@/types/todo';

interface EditProps {
    todo: Todo;
}

export default function Edit({ todo }: EditProps) {
    return <EditTodo todo={todo} />;
}
