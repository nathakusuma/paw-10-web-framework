<?php

use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Todo routes
    Route::get('dashboard', [TodoController::class, 'index'])->name('dashboard');
    Route::get('todos/create', [TodoController::class, 'create'])->name('todos.create');
    Route::post('todos', [TodoController::class, 'store'])->name('todos.store');
    Route::get('todos/{todo}/edit', [TodoController::class, 'edit'])->name('todos.edit');
    Route::put('todos/{todo}', [TodoController::class, 'update'])->name('todos.update');
    Route::delete('todos/{todo}', [TodoController::class, 'destroy'])->name('todos.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
