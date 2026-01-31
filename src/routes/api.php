<?php

use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\InternalApiKey;
use Illuminate\Support\Facades\Route;

// Internal routes for service-to-service communication (SSO)
Route::middleware([InternalApiKey::class])->prefix('internal')->group(function () {
    Route::post('/auth/check', [UserController::class, 'authorize']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'showById']);
    Route::put('/users/{id}', [UserController::class, 'updateById']);
    Route::delete('/users/{id}', [UserController::class, 'destroyById']);

    // Role management (internal)
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/users/{userId}/roles', [RoleController::class, 'getUserRoles']);
    Route::post('/users/{userId}/roles', [RoleController::class, 'assignRole']);
    Route::delete('/users/{userId}/roles/{roleName}', [RoleController::class, 'removeRole']);
});

// OAuth protected routes
Route::middleware(['auth:api', 'scope:users.read'])->post('/auth/check', [UserController::class, 'authorize']);

Route::middleware(['auth:api', 'scope:users.read'])->get('/users', [UserController::class, 'index']);

Route::middleware(['auth:api', 'scope:users.read'])->get('/users/{user}', [UserController::class, 'show']);

Route::middleware(['auth:api', 'scope:users.read'])->post('/users', [UserController::class, 'store']);

Route::middleware(['auth:api', 'scope:users.read'])->put('/users/{user}', [UserController::class, 'update']);

Route::middleware(['auth:api', 'scope:users.read'])->delete('/users/{user}', [UserController::class, 'destroy']);
