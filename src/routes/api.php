<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:api', 'scope:users.read'])->post('/auth/check', [UserController::class, 'authorize']);

Route::middleware(['auth:api', 'scope:users.read'])->get('/users', [UserController::class, 'index']);

Route::middleware(['auth:api', 'scope:users.read'])->get('/users/{user}', [UserController::class, 'show']);

Route::middleware(['auth:api', 'scope:users.read'])->post('/users', [UserController::class, 'store']);

Route::middleware(['auth:api', 'scope:users.read'])->put('/users/{user}', [UserController::class, 'update']);

Route::middleware(['auth:api', 'scope:users.read'])->delete('/users/{user}', [UserController::class, 'destroy']);
