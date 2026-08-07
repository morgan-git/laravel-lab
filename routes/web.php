<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SessionsController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/ideas.php';
require __DIR__.'/feeds.php';
require __DIR__.'/admin.php';

Route::get('/', [FeedController::class, 'index']);

Route::middleware('guest')->group(function (): void {

    Route::get('/register', [RegisteredUserController::class, 'create']);
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [SessionsController::class, 'create'])->name('login');
    Route::post('/login', [SessionsController::class, 'store']);
});

Route::delete('/logout', [SessionsController::class, 'destroy'])->middleware('auth');

Route::view('/contact', 'contact');
Route::view('/about', 'about');

Route::get('/admin', fn () => 'Private admin area demo')->can('view-admin');
