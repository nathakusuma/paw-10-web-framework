<?php

use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [TodoController::class, 'index'])->name('dashboard');
    Route::get('todos/create', [TodoController::class, 'create'])->name('todos.create');
    Route::post('todos', [TodoController::class, 'store'])->name('todos.store');
    Route::put('todos/{todo}', [TodoController::class, 'update'])->name('todos.update');
    Route::delete('todos/{todo}', [TodoController::class, 'destroy'])->name('todos.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
