<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Todo>
 */
class TodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(rand(3, 8)),
            'description' => $this->faker->boolean(70) ? $this->faker->paragraph(rand(1, 3)) : null,
            'is_completed' => $this->faker->boolean(30),
            'created_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'updated_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Indicate that the todo is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
        ]);
    }

    /**
     * Indicate that the todo is not completed.
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => false,
        ]);
    }

    /**
     * Create todo with specific creation time for testing
     */
    public function createdAt($datetime): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $datetime,
            'updated_at' => $datetime,
        ]);
    }
}

