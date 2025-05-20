<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodoController extends Controller
{
    public function index(): Response
    {
        $todos = auth()->user()->todos()->latest()->get();

        return Inertia::render('dashboard', [
            'todos' => $todos
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Todos/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $request->user()->todos()->create($validated);

        return redirect()->route('todos.index');
    }

    public function update(Request $request, Todo $todo): RedirectResponse
    {
        // Check if the todo belongs to the authenticated user
        $this->authorize('update', $todo);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_completed' => 'boolean',
        ]);

        $todo->update($validated);

        return redirect()->route('todos.index');
    }

    public function destroy(Todo $todo): RedirectResponse
    {
        // Check if the todo belongs to the authenticated user
        $this->authorize('delete', $todo);

        $todo->delete();

        return redirect()->route('todos.index');
    }

    public function toggleComplete(Todo $todo): RedirectResponse
    {
        // Check authorization
        $this->authorize('update', $todo);

        $todo->update([
            'completed' => !$todo->completed,
        ]);

        return redirect(route('todos.index'));
    }
}
