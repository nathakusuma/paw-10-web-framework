<?php

namespace Database\Seeders;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a demo user with a known password
        $demoUser = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create 40 todos for the demo user (mix of completed and incomplete)
        Todo::factory()
            ->count(25)
            ->for($demoUser)
            ->incomplete()
            ->create();

        Todo::factory()
            ->count(15)
            ->for($demoUser)
            ->completed()
            ->create();

        // Create additional users with their own todos
        User::factory()
            ->count(5)
            ->has(
                Todo::factory()
                    ->count(10)
                    ->state(function (array $attributes, User $user) {
                        return ['user_id' => $user->id];
                    })
            )
            ->create();

        // Make sure the test user from the original seeder is still created
        if (!User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
