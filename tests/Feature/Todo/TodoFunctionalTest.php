<?php

namespace Tests\Feature\Todo;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TodoFunctionalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /**
     * ========================================
     * CREATE FUNCTIONALITY TESTS
     * ========================================
     */

    #[Test]
    public function authenticated_user_can_view_create_todo_page()
    {
        $response = $this->actingAs($this->user)->get('/todos/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('todos/create'));
    }

    #[Test]
    public function guest_cannot_view_create_todo_page()
    {
        $response = $this->get('/todos/create');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_user_can_create_todo_with_valid_data()
    {
        $todoData = [
            'title' => 'Test Todo',
            'description' => 'This is a test todo description',
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertRedirect('/dashboard?filter=all');

        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => 'Test Todo',
            'description' => 'This is a test todo description',
            'is_completed' => false
        ]);
    }

    #[Test]
    public function authenticated_user_can_create_todo_without_description()
    {
        $todoData = [
            'title' => 'Test Todo Without Description',
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertRedirect('/dashboard?filter=all');

        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => 'Test Todo Without Description',
            'description' => null,
            'is_completed' => false
        ]);
    }

    #[Test]
    public function create_todo_requires_title()
    {
        $todoData = [
            'description' => 'Description without title',
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertSessionHasErrors(['title']);
        $this->assertDatabaseMissing('todos', [
            'user_id' => $this->user->id,
            'description' => 'Description without title'
        ]);
    }

    #[Test]
    public function create_todo_title_cannot_exceed_255_characters()
    {
        $todoData = [
            'title' => str_repeat('a', 256),
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertSessionHasErrors(['title']);
        $this->assertDatabaseMissing('todos', [
            'user_id' => $this->user->id,
            'title' => str_repeat('a', 256)
        ]);
    }

    #[Test]
    public function guest_cannot_create_todo()
    {
        $todoData = [
            'title' => 'Unauthorized Todo',
            'filter' => 'all'
        ];

        $response = $this->post('/todos', $todoData);

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('todos', [
            'title' => 'Unauthorized Todo'
        ]);
    }

    /**
     * ========================================
     * READ FUNCTIONALITY TESTS
     * ========================================
     */

    #[Test]
    public function authenticated_user_can_view_dashboard_with_todos()
    {
        // Create test todos with specific timestamps to ensure predictable ordering
        $activeTodo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Active Todo',
            'is_completed' => false,
            'created_at' => now()->subMinutes(10), // Created 10 minutes ago
        ]);

        $completedTodo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Completed Todo',
            'is_completed' => true,
            'created_at' => now(), // Created now (more recent)
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
        $page->component('dashboard')
            ->has('todos.data', 2)
            ->where('todos.data.0.title', 'Completed Todo') // Latest first (created more recently)
            ->where('todos.data.1.title', 'Active Todo')
        );
    }

    #[Test]
    public function user_can_filter_todos_by_active_status()
    {
        // Create test todos
        Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Active Todo',
            'is_completed' => false
        ]);

        Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Completed Todo',
            'is_completed' => true
        ]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard?filter=active');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
        $page->component('dashboard')
            ->has('todos.data', 1)
            ->where('todos.data.0.title', 'Active Todo')
            ->where('filter', 'active')
        );
    }

    #[Test]
    public function user_can_filter_todos_by_completed_status()
    {
        // Create test todos
        Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Active Todo',
            'is_completed' => false
        ]);

        Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Completed Todo',
            'is_completed' => true
        ]);

        $response = $this->actingAs($this->user)
            ->get('/dashboard?filter=completed');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
        $page->component('dashboard')
            ->has('todos.data', 1)
            ->where('todos.data.0.title', 'Completed Todo')
            ->where('filter', 'completed')
        );
    }

    #[Test]
    public function todos_are_paginated_correctly()
    {
        // Create 25 todos (more than default pagination)
        Todo::factory()->count(25)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
        $page->component('dashboard')
            ->has('todos.data', 10) // Default per_page is 10
            ->where('todos.total', 25)
            ->where('todos.last_page', 3)
        );
    }

    #[Test]
    public function user_can_only_see_their_own_todos()
    {
        // Create todos for different users
        $userTodo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'User Todo'
        ]);

        $otherUserTodo = Todo::factory()->create([
            'user_id' => $this->otherUser->id,
            'title' => 'Other User Todo'
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
        $page->component('dashboard')
            ->has('todos.data', 1)
            ->where('todos.data.0.title', 'User Todo')
        );
    }

    #[Test]
    public function guest_cannot_view_dashboard()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    /**
     * ========================================
     * UPDATE FUNCTIONALITY TESTS
     * ========================================
     */

    #[Test]
    public function user_can_view_edit_page_for_their_todo()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title'
        ]);

        $response = $this->actingAs($this->user)
            ->get("/todos/{$todo->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
        $page->component('todos/edit')
            ->where('todo.id', $todo->id)
            ->where('todo.title', 'Original Title')
        );
    }

    #[Test]
    public function user_cannot_view_edit_page_for_other_users_todo()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->otherUser->id,
            'title' => 'Other User Todo'
        ]);

        $response = $this->actingAs($this->user)
            ->get("/todos/{$todo->id}/edit");

        $response->assertStatus(403);
    }

    #[Test]
    public function user_can_update_their_todo_with_valid_data()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'description' => 'Original Description',
            'is_completed' => false
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'is_completed' => true,
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", $updateData);

        $response->assertRedirect('/dashboard?filter=all');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'is_completed' => true
        ]);
    }

    #[Test]
    public function user_can_toggle_todo_completion_status()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Todo',
            'is_completed' => false
        ]);

        $updateData = [
            'title' => $todo->title,
            'description' => $todo->description,
            'is_completed' => true,
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", $updateData);

        $response->assertRedirect('/dashboard?filter=all');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'is_completed' => true
        ]);
    }

    #[Test]
    public function user_cannot_update_other_users_todo()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->otherUser->id,
            'title' => 'Other User Todo'
        ]);

        $updateData = [
            'title' => 'Hacked Title',
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", $updateData);

        $response->assertStatus(403);

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Other User Todo' // Should remain unchanged
        ]);
    }

    #[Test]
    public function update_todo_requires_valid_title()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title'
        ]);

        $updateData = [
            'title' => '', // Empty title
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", $updateData);

        $response->assertSessionHasErrors(['title']);

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Original Title' // Should remain unchanged
        ]);
    }

    #[Test]
    public function update_todo_title_cannot_exceed_255_characters()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title'
        ]);

        $updateData = [
            'title' => str_repeat('a', 256),
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", $updateData);

        $response->assertSessionHasErrors(['title']);

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Original Title'
        ]);
    }

    /**
     * ========================================
     * DELETE FUNCTIONALITY TESTS
     * ========================================
     */

    #[Test]
    public function user_can_delete_their_own_todo()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Todo to Delete'
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/todos/{$todo->id}");

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseMissing('todos', [
            'id' => $todo->id
        ]);
    }

    #[Test]
    public function user_cannot_delete_other_users_todo()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->otherUser->id,
            'title' => 'Other User Todo'
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/todos/{$todo->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Other User Todo'
        ]);
    }

    #[Test]
    public function delete_nonexistent_todo_returns_404()
    {
        $nonexistentId = 99999;

        $response = $this->actingAs($this->user)
            ->delete("/todos/{$nonexistentId}");

        $response->assertStatus(404);
    }

    #[Test]
    public function guest_cannot_delete_todo()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Protected Todo'
        ]);

        $response = $this->delete("/todos/{$todo->id}");

        $response->assertRedirect('/login');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Protected Todo'
        ]);
    }

    /**
     * ========================================
     * ADDITIONAL INTEGRATION TESTS
     * ========================================
     */

    #[Test]
    public function complete_todo_workflow_create_read_update_delete()
    {
        // 1. CREATE: Create a new todo
        $createData = [
            'title' => 'Workflow Test Todo',
            'description' => 'Testing complete workflow',
            'filter' => 'all'
        ];

        $createResponse = $this->actingAs($this->user)
            ->post('/todos', $createData);

        $createResponse->assertRedirect('/dashboard?filter=all');

        $todo = Todo::where('title', 'Workflow Test Todo')->first();
        $this->assertNotNull($todo);

        // 2. READ: Verify todo appears in dashboard
        $readResponse = $this->actingAs($this->user)->get('/dashboard');
        $readResponse->assertInertia(fn ($page) =>
        $page->has('todos.data')
            ->where('todos.data.0.title', 'Workflow Test Todo')
        );

        // 3. UPDATE: Update the todo
        $updateData = [
            'title' => 'Updated Workflow Todo',
            'description' => 'Updated description',
            'is_completed' => true,
            'filter' => 'all'
        ];

        $updateResponse = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", $updateData);

        $updateResponse->assertRedirect('/dashboard?filter=all');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Updated Workflow Todo',
            'is_completed' => true
        ]);

        // 4. DELETE: Delete the todo
        $deleteResponse = $this->actingAs($this->user)
            ->delete("/todos/{$todo->id}");

        $deleteResponse->assertRedirect('/dashboard');

        $this->assertDatabaseMissing('todos', [
            'id' => $todo->id
        ]);
    }

    #[Test]
    public function filter_persistence_across_operations()
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'is_completed' => false
        ]);

        // Create with active filter
        $createData = [
            'title' => 'New Active Todo',
            'filter' => 'active'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $createData);

        $response->assertRedirect('/dashboard?filter=active');

        // Update with active filter
        $updateData = [
            'title' => $todo->title,
            'description' => $todo->description,
            'is_completed' => true,
            'filter' => 'active'
        ];

        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", $updateData);

        $response->assertRedirect('/dashboard?filter=active');
    }

    #[Test]
    public function bulk_operations_with_multiple_todos()
    {
        // Create multiple todos
        $todos = Todo::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'is_completed' => false
        ]);

        // Verify all are displayed
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertInertia(fn ($page) =>
        $page->has('todos.data', 5)
        );

        // Update multiple todos to completed
        foreach ($todos as $todo) {
            $this->actingAs($this->user)->put("/todos/{$todo->id}", [
                'title' => $todo->title,
                'description' => $todo->description,
                'is_completed' => true,
                'filter' => 'all'
            ]);
        }

        // Verify all are completed
        $response = $this->actingAs($this->user)->get('/dashboard?filter=completed');
        $response->assertInertia(fn ($page) =>
        $page->has('todos.data', 5)
        );

        // Delete all todos
        foreach ($todos as $todo) {
            $this->actingAs($this->user)->delete("/todos/{$todo->id}");
        }

        // Verify all are deleted
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertInertia(fn ($page) =>
        $page->has('todos.data', 0)
        );
    }
}
