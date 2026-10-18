<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/posts/{post}', [PostController::class, 'show']);
Route::post('/posts/{post}/publish', [PostController::class, 'publish']);
