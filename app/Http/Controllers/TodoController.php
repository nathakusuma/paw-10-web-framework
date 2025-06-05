<?php

namespace App\Http\Controllers;

use App\Http\Requests\TodoRequest;
use App\Models\Todo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodoController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->input('filter', 'all');
        $perPage = $request->input('per_page', 10);

        $perPage = is_numeric($perPage) && $perPage > 0 && $perPage <= 100 ? (int)$perPage : 10;

        $query = auth()->user()->todos();

        if ($filter === 'active') {
            $query->where('is_completed', false);
        } elseif ($filter === 'completed') {
            $query->where('is_completed', true);
        }

        $todos = $query->latest('created_at')->paginate($perPage)->withQueryString();

        return Inertia::render('dashboard', [
            'todos' => $todos,
            'filter' => $filter
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('todos/create');
    }

    public function store(TodoRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $todoData = collect($validated)->except(['filter'])->toArray();

        $request->user()->todos()->create($todoData);

        return redirect()->route('dashboard', ['filter' => $validated['filter'] ?? 'all']);
    }

    public function edit(Todo $todo, Request $request): Response
    {
        $this->authorize('update', $todo);

        return Inertia::render('todos/edit', [
            'todo' => $todo,
            'filter' => $request->query('filter', 'all')
        ]);
    }

    public function update(TodoRequest $request, Todo $todo): RedirectResponse
    {
        $this->authorize('update', $todo);

        $validated = $request->validated();

        $todoData = collect($validated)->except(['filter'])->toArray();

        $todo->update($todoData);

        return redirect()->route('dashboard', ['filter' => $validated['filter'] ?? 'all']);
    }

    public function destroy(Todo $todo): RedirectResponse
    {
        $this->authorize('delete', $todo);

        $todo->delete();

        return redirect()->route('dashboard');
    }
}
