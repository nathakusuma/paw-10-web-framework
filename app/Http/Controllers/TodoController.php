<?php

namespace App\Http\Controllers;

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

        $query = auth()->user()->todos();

        if ($filter === 'active') {
            $query->where('is_completed', false);
        } else if ($filter === 'completed') {
            $query->where('is_completed', true);
        }

        $todos = $query->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('dashboard', [
            'todos' => $todos,
            'filter' => $filter
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('todos/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $request->user()->todos()->create($validated);

        return redirect()->route('dashboard', ['filter' => $request->query('filter', 'all')]);
    }

    public function edit(Todo $todo, Request $request): Response
    {
        $this->authorize('update', $todo);

        return Inertia::render('todos/edit', [
            'todo' => $todo,
            'filter' => $request->query('filter', 'all')
        ]);
    }

    public function update(Request $request, Todo $todo): RedirectResponse
    {
        $this->authorize('update', $todo);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_completed' => 'boolean',
        ]);

        $todo->update($validated);

        return redirect()->route('dashboard', ['filter' => $request->query('filter', 'all')]);
    }

    public function destroy(Todo $todo): RedirectResponse
    {
        $this->authorize('delete', $todo);

        $todo->delete();

        return redirect()->route('dashboard');
    }
}
