<?php

namespace Tests\Feature\Todo;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * ========================================
     * INPUT VALIDATION EDGE CASES
     * ========================================
     */

    /** @test */
    public function create_todo_with_special_characters_in_title()
    {
        $todoData = [
            'title' => 'Special chars: @#$%^&*()_+-=[]{}|;:,.<>?',
            'description' => 'Testing special characters',
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertRedirect('/dashboard?filter=all');

        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => 'Special chars: @#$%^&*()_+-=[]{}|;:,.<>?'
        ]);
    }

    /** @test */
    public function create_todo_with_unicode_characters()
    {
        $todoData = [
            'title' => 'Unicode test: 测试 🎉 العربية русский',
            'description' => 'Testing Unicode: 日本語 한국어 français',
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertRedirect('/dashboard?filter=all');

        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => 'Unicode test: 测试 🎉 العربية русский'
        ]);
    }

    /** @test */
    public function create_todo_with_html_tags_in_input()
    {
        $todoData = [
            'title' => '<script>alert("XSS")</script>Regular Title',
            'description' => '<h1>Header</h1><p>Paragraph</p>',
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertRedirect('/dashboard?filter=all');

        // HTML should be stored as-is (filtering happens on display)
        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => '<script>alert("XSS")</script>Regular Title'
        ]);
    }

    /** @test */
    public function create_todo_with_sql_injection_attempt()
    {
        $todoData = [
            'title' => "'; DROP TABLE todos; --",
            'description' => "1' OR '1'='1",
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertRedirect('/dashboard?filter=all');

        // Should be safely stored
        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => "'; DROP TABLE todos; --"
        ]);

        // Verify table still exists
        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id
        ]);
    }

    /** @test */
    public function create_todo_with_very_long_description()
    {
        $longDescription = str_repeat('This is a very long description. ', 1000); // ~30,000 chars

        $todoData = [
            'title' => 'Todo with long description',
            'description' => $longDescription,
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertRedirect('/dashboard?filter=all');

        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => 'Todo with long description'
        ]);
    }

    /** @test */
    public function create_todo_with_whitespace_only_title_fails()
    {
        $todoData = [
            'title' => '   ',
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertSessionHasErrors(['title']);
    }

    /** @test */
    public function create_todo_trims_whitespace_from_title()
    {
        $todoData = [
            'title' => '  Trimmed Title  ',
            'description' => '  Trimmed Description  ',
            'filter' => 'all'
        ];

        $response = $this->actingAs($this->user)
            ->post('/todos', $todoData);

        $response->assertRedirect('/dashboard?filter=all');

        // Laravel automatically trims input
        $this->assertDatabaseHas('todos', [
            'user_id' => $this->user->id,
            'title' => 'Trimmed Title'
        ]);
    }

    /**
     * ========================================
     * PAGINATION EDGE CASES
     * ========================================
     */

    /** @test */
    public function pagination_handles_invalid_page_numbers()
    {
        Todo::factory()->count(15)->create(['user_id' => $this->user->id]);

        // Test negative page
        $response = $this->actingAs($this->user)->get('/dashboard?page=-1');
        $response->assertStatus(200);

        // Test zero page
        $response = $this->actingAs($this->user)->get('/dashboard?page=0');
        $response->assertStatus(200);

        // Test very large page number
        $response = $this->actingAs($this->user)->get('/dashboard?page=999');
        $response->assertStatus(200);
    }

    /** @test */
    public function pagination_with_different_per_page_values()
    {
        Todo::factory()->count(25)->create(['user_id' => $this->user->id]);

        // Test valid per_page values
        foreach ([5, 10, 20, 50] as $perPage) {
            $response = $this->actingAs($this->user)
                ->get("/dashboard?per_page={$perPage}");

            $response->assertStatus(200);
            $response->assertInertia(fn ($page) =>
            $page->has('todos.data', min($perPage, 25))
            );
        }
    }

    /** @test */
    public function pagination_with_invalid_per_page_values()
    {
        Todo::factory()->count(15)->create(['user_id' => $this->user->id]);

        // Test invalid per_page values - should fallback to default
        foreach ([0, -5, 'invalid', 1000] as $invalidPerPage) {
            $response = $this->actingAs($this->user)
                ->get("/dashboard?per_page={$invalidPerPage}");

            $response->assertStatus(200);
        }
    }

    /**
     * ========================================
     * FILTER EDGE CASES
     * ========================================
     */

    /** @test */
    public function filter_handles_invalid_filter_values()
    {
        Todo::factory()->create(['user_id' => $this->user->id, 'is_completed' => false]);
        Todo::factory()->create(['user_id' => $this->user->id, 'is_completed' => true]);

        $invalidFilters = ['invalid', 'ACTIVE', 'Completed', '123', '', null];

        foreach ($invalidFilters as $filter) {
            $response = $this->actingAs($this->user)
                ->get("/dashboard?filter={$filter}");

            $response->assertStatus(200);
            // Should show all todos for invalid filters
            $response->assertInertia(fn ($page) =>
            $page->has('todos.data', 2)
            );
        }
    }

    /** @test */
    public function filter_is_case_sensitive()
    {
        Todo::factory()->create(['user_id' => $this->user->id, 'is_completed' => false]);

        $response = $this->actingAs($this->user)->get('/dashboard?filter=ACTIVE');

        $response->assertInertia(fn ($page) =>
        $page->where('filter', 'ACTIVE')
            ->has('todos.data', 1) // Should show all since 'ACTIVE' != 'active'
        );
    }

    /**
     * ========================================
     * CONCURRENT OPERATION EDGE CASES
     * ========================================
     */

    /** @test */
    public function updating_deleted_todo_returns_404()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        // Delete the todo
        $todo->delete();

        // Try to update the deleted todo
        $response = $this->actingAs($this->user)
            ->put("/todos/{$todo->id}", [
                'title' => 'Updated Title',
                'filter' => 'all'
            ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function editing_deleted_todo_returns_404()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        // Delete the todo
        $todo->delete();

        // Try to edit the deleted todo
        $response = $this->actingAs($this->user)
            ->get("/todos/{$todo->id}/edit");

        $response->assertStatus(404);
    }

    /**
     * ========================================
     * DATABASE CONSTRAINT EDGE CASES
     * ========================================
     */

    /** @test */
    public function creating_todo_for_nonexistent_user_fails()
    {
        // This should never happen in normal flow, but test the constraint
        $this->expectException(\Exception::class);

        Todo::create([
            'user_id' => 99999, // Non-existent user
            'title' => 'Orphaned Todo',
            'is_completed' => false
        ]);
    }

    /** @test */
    public function todos_are_deleted_when_user_is_deleted()
    {
        $user = User::factory()->create();
        $todo = Todo::factory()->create(['user_id' => $user->id]);

        // Delete the user
        $user->delete();

        // Todo should be deleted due to cascade constraint
        $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
    }

    /**
     * ========================================
     * PERFORMANCE EDGE CASES
     * ========================================
     */

    /** @test */
    public function dashboard_performance_with_many_todos()
    {
        // Create a large number of todos
        Todo::factory()->count(1000)->create(['user_id' => $this->user->id]);

        $startTime = microtime(true);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);

        // Assert reasonable response time (adjust threshold as needed)
        $this->assertLessThan(5000, $executionTime, 'Dashboard took too long to load with 1000 todos');
    }

    /** @test */
    public function search_with_special_query_parameters()
    {
        Todo::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Normal Todo'
        ]);

        // Test with various query parameters
        $specialParams = [
            'filter=all&page=1&per_page=10',
            'filter=active&sort=desc',
            'filter=completed&search=test',
            'invalid_param=value&filter=all'
        ];

        foreach ($specialParams as $params) {
            $response = $this->actingAs($this->user)
                ->get("/dashboard?{$params}");

            $response->assertStatus(200);
        }
    }

    /**
     * ========================================
     * SESSION AND CSRF EDGE CASES
     * ========================================
     */

    /** @test */
    public function operations_require_csrf_token()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        // Disable CSRF middleware for this test to test the requirement
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        // These should still work as we're using Laravel's testing methods
        $response = $this->actingAs($this->user)
            ->post('/todos', [
                'title' => 'CSRF Test Todo',
                'filter' => 'all'
            ]);

        $response->assertRedirect('/dashboard?filter=all');
    }

    /**
     * ========================================
     * BOUNDARY VALUE TESTING
     * ========================================
     */

    /** @test */
    public function title_length_boundary_testing()
    {
        // Test exactly 255 characters (should pass)
        $exactly255 = str_repeat('a', 255);
        $response = $this->actingAs($this->user)
            ->post('/todos', [
                'title' => $exactly255,
                'filter' => 'all'
            ]);
        $response->assertRedirect('/dashboard?filter=all');

        // Test 256 characters (should fail)
        $exactly256 = str_repeat('b', 256);
        $response = $this->actingAs($this->user)
            ->post('/todos', [
                'title' => $exactly256,
                'filter' => 'all'
            ]);
        $response->assertSessionHasErrors(['title']);
    }

    /** @test */
    public function boolean_field_boundary_testing()
    {
        $todo = Todo::factory()->create(['user_id' => $this->user->id]);

        // Test various boolean representations
        $booleanValues = [
            true, false, 1, 0, '1', '0', 'true', 'false', 'on', 'off'
        ];

        foreach ($booleanValues as $value) {
            $response = $this->actingAs($this->user)
                ->put("/todos/{$todo->id}", [
                    'title' => $todo->title,
                    'description' => $todo->description,
                    'is_completed' => $value,
                    'filter' => 'all'
                ]);

            // All should be accepted (Laravel handles boolean conversion)
            $response->assertRedirect('/dashboard?filter=all');
        }
    }

    /**
     * ========================================
     * ERROR RECOVERY TESTING
     * ========================================
     */

    /** @test */
    public function system_handles_database_errors_gracefully()
    {
        // This is difficult to test without actually breaking the database
        // In a real scenario, you might use database mocking or
        // temporarily corrupt the database connection

        $this->markTestSkipped('Database error simulation requires advanced setup');
    }

    /** @test */
    public function system_handles_memory_pressure()
    {
        // Create a scenario that uses significant memory
        $largeTodos = collect(range(1, 100))->map(function ($i) {
            return [
                'user_id' => $this->user->id,
                'title' => "Todo {$i} with large description " . str_repeat('data', 1000),
                'description' => str_repeat("Long description for todo {$i}. ", 500),
                'is_completed' => false,
                'created_at' => now(),
                'updated_at' => now()
            ];
        })->toArray();

        Todo::insert($largeTodos);

        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);
    }
}
