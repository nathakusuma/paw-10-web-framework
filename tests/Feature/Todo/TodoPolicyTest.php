<?php

namespace Tests\Feature\Todo;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TodoPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /**
     * ========================================
     * POLICY AUTHORIZATION TESTS
     * ========================================
     */

    #[Test]
    public function user_can_update_own_todo()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", [
                'title' => 'Updated Title',
                'description' => 'Updated Description',
                'is_completed' => true,
                'filter' => 'all'
            ]);

        $response->assertRedirect('/dashboard?filter=all');
        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Updated Title'
        ]);
    }

    #[Test]
    public function user_cannot_update_other_users_todo()
    {
        $todo = Todo::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", [
                'title' => 'Hacked Title',
                'filter' => 'all'
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => $todo->title // Original title unchanged
        ]);
    }

    #[Test]
    public function user_can_delete_own_todo()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->delete("/todos/{$todo->id}");

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
    }

    #[Test]
    public function user_cannot_delete_other_users_todo()
    {
        $todo = Todo::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->delete("/todos/{$todo->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('todos', ['id' => $todo->id]);
    }

    #[Test]
    public function user_can_view_edit_form_for_own_todo()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get("/todos/{$todo->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
        $page->component('todos/edit')
            ->where('todo.id', $todo->id)
        );
    }

    #[Test]
    public function user_cannot_view_edit_form_for_other_users_todo()
    {
        $todo = Todo::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->get("/todos/{$todo->id}/edit");

        $response->assertStatus(403);
    }

    /**
     * ========================================
     * MASS ASSIGNMENT PROTECTION TESTS
     * ========================================
     */

    #[Test]
    public function user_cannot_change_todo_ownership_via_update()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", [
                'title' => 'Updated Title',
                'user_id' => $this->otherUser->id, // Attempt to change ownership
                'filter' => 'all'
            ]);

        $response->assertRedirect('/dashboard?filter=all');

        // Ownership should remain unchanged
        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'user_id' => $this->user->id, // Still owned by original user
            'title' => 'Updated Title'
        ]);
    }

    #[Test]
    public function user_cannot_set_arbitrary_id_when_creating_todo()
    {
        $response = $this->actingAs($this->user)
            ->post('/todos', [
                'id' => 99999, // Attempt to set specific ID
                'title' => 'Test Todo',
                'user_id' => $this->otherUser->id, // Attempt to create for other user
                'filter' => 'all'
            ]);

        $response->assertRedirect('/dashboard?filter=all');

        $todo = Todo::where('title', 'Test Todo')->first();
        $this->assertNotNull($todo);
        $this->assertNotEquals(99999, $todo->id); // ID should be auto-generated
        $this->assertEquals($this->user->id, $todo->user_id); // Should be owned by authenticated user
    }

    /**
     * ========================================
     * ROUTE MODEL BINDING TESTS
     * ========================================
     */

    #[Test]
    public function nonexistent_todo_returns_404_for_update()
    {
        $response = $this->actingAs($this->user)
            ->put('/todos/99999', [
                'title' => 'Updated Title',
                'filter' => 'all'
            ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function nonexistent_todo_returns_404_for_delete()
    {
        $response = $this->actingAs($this->user)
            ->delete('/todos/99999');

        $response->assertStatus(404);
    }

    #[Test]
    public function nonexistent_todo_returns_404_for_edit()
    {
        $response = $this->actingAs($this->user)
            ->get('/todos/99999/edit');

        $response->assertStatus(404);
    }

    #[Test]
    public function invalid_todo_id_format_returns_404()
    {
        $invalidIds = ['abc', 'null', '1.5', '-1', ''];

        foreach ($invalidIds as $id) {
            $response = $this->actingAs($this->user)
                ->get("/todos/{$id}/edit");

            $response->assertStatus(404);
        }
    }

    /**
     * ========================================
     * MIDDLEWARE AUTHENTICATION TESTS
     * ========================================
     */

    #[Test]
    public function guest_redirected_to_login_for_all_protected_routes()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        $protectedRoutes = [
            ['GET', '/dashboard'],
            ['GET', '/todos/create'],
            ['POST', '/todos'],
            ['GET', "/todos/{$todo->id}/edit"],
            ['PUT', "/todos/{$todo->id}"],
            ['DELETE', "/todos/{$todo->id}"],
        ];

        foreach ($protectedRoutes as [$method, $route]) {
            $response = $this->call($method, $route, [
                'title' => 'Test',
                'filter' => 'all'
            ]);

            $response->assertRedirect('/login');
        }
    }

    /**
     * ========================================
     * SESSION AND STATE MANAGEMENT TESTS
     * ========================================
     */

    #[Test]
    public function filter_state_preserved_across_operations()
    {
        $activeFilter = 'active';

        // Create with filter
        $response = $this->actingAs($this->user)
            ->post('/todos', [
                'title' => 'Test Todo',
                'filter' => $activeFilter
            ]);

        $response->assertRedirect("/dashboard?filter={$activeFilter}");

        // Update with filter
        $todo = Todo::where('title', 'Test Todo')->first();
        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", [
                'title' => 'Updated Todo',
                'description' => $todo->description,
                'is_completed' => true,
                'filter' => $activeFilter
            ]);

        $response->assertRedirect("/dashboard?filter={$activeFilter}");
    }

    #[Test]
    public function user_session_isolation()
    {
        // Create todos for both users
        $userTodo = Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'User Todo'
        ]);

        $otherUserTodo = Todo::factory()->create([
            'user_id' => $this->otherUser->id,
            'title' => 'Other User Todo'
        ]);

        // User 1 should only see their todo
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertInertia(fn ($page) =>
        $page->has('todos.data', 1)
            ->where('todos.data.0.title', 'User Todo')
        );

        // User 2 should only see their todo
        $response = $this->actingAs($this->otherUser)->get('/dashboard');
        $response->assertInertia(fn ($page) =>
        $page->has('todos.data', 1)
            ->where('todos.data.0.title', 'Other User Todo')
        );
    }

    /**
     * ========================================
     * CONCURRENT ACCESS TESTS
     * ========================================
     */

    #[Test]
    public function multiple_users_can_operate_simultaneously()
    {
        // Simulate concurrent operations
        $user1Todo = Todo::factory()->create(['user_id' => $this->user->id]);
        $user2Todo = Todo::factory()->create(['user_id' => $this->otherUser->id]);

        // User 1 updates their todo
        $response1 = $this->actingAs($this->user)
            ->put("/todos/{$user1Todo->id}", [
                'title' => 'User 1 Updated',
                'description' => $user1Todo->description,
                'is_completed' => true,
                'filter' => 'all'
            ]);

        // User 2 updates their todo
        $response2 = $this->actingAs($this->otherUser)
            ->put("/todos/{$user2Todo->id}", [
                'title' => 'User 2 Updated',
                'description' => $user2Todo->description,
                'is_completed' => false,
                'filter' => 'all'
            ]);

        // Both operations should succeed
        $response1->assertRedirect('/dashboard?filter=all');
        $response2->assertRedirect('/dashboard?filter=all');

        // Verify changes
        $this->assertDatabaseHas('todos', [
            'id' => $user1Todo->id,
            'title' => 'User 1 Updated',
            'is_completed' => true
        ]);

        $this->assertDatabaseHas('todos', [
            'id' => $user2Todo->id,
            'title' => 'User 2 Updated',
            'is_completed' => false
        ]);
    }

    /**
     * ========================================
     * DATA INTEGRITY TESTS
     * ========================================
     */

    #[Test]
    public function todo_timestamps_are_updated_correctly()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);
        $originalUpdatedAt = $todo->updated_at;

        // Wait a moment to ensure timestamp difference
        sleep(1);

        $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", [
                'title' => 'Updated Title',
                'description' => $todo->description,
                'is_completed' => $todo->is_completed,
                'filter' => 'all'
            ]);

        $updatedTodo = Todo::find($todo->id);
        $this->assertNotEquals($originalUpdatedAt, $updatedTodo->updated_at);
        $this->assertTrue($updatedTodo->updated_at > $originalUpdatedAt);
    }

    #[Test]
    public function todo_created_at_timestamp_is_immutable()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);
        $originalCreatedAt = $todo->created_at;

        $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", [
                'title' => 'Updated Title',
                'description' => $todo->description,
                'is_completed' => !$todo->is_completed,
                'filter' => 'all'
            ]);

        $updatedTodo = Todo::find($todo->id);
        $this->assertEquals($originalCreatedAt, $updatedTodo->created_at);
    }

    /**
     * ========================================
     * RESPONSE FORMAT TESTS
     * ========================================
     */

    #[Test]
    public function dashboard_returns_correct_inertia_structure()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(fn ($page) =>
        $page->component('dashboard')
            ->has('todos')
            ->has('todos.data')
            ->has('todos.current_page')
            ->has('todos.total')
            ->has('todos.last_page')
            ->has('filter')
        );
    }

    #[Test]
    public function edit_page_returns_correct_inertia_structure()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get("/todos/{$todo->id}/edit");

        $response->assertInertia(fn ($page) =>
        $page->component('todos/edit')
            ->has('todo')
            ->has('todo.id')
            ->has('todo.title')
            ->has('todo.description')
            ->has('todo.is_completed')
            ->has('filter')
        );
    }
}
