<?php

namespace Database\Seeders;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TodoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all existing users
        $users = User::all();

        if ($users->isEmpty()) {
            // If no users exist, create one
            $users = User::factory()->count(1)->create();
        }

        // Create todos for each user
        foreach ($users as $user) {
            // Create a mix of completed and incomplete todos
            $completedCount = rand(5, 15);
            $incompleteCount = rand(10, 25);

            // Create completed todos
            Todo::factory()
                ->count($completedCount)
                ->for($user)
                ->completed()
                ->create();

            // Create incomplete todos
            Todo::factory()
                ->count($incompleteCount)
                ->for($user)
                ->incomplete()
                ->create();
        }
    }
}
