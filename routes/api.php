<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\ForgotPasswordController;

// Public authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/auth/reset-password', [ForgotPasswordController::class, 'resetPassword']);

// Protected routes (requires auth:sanctum)
Route::middleware(['auth:sanctum'])->group(function () {
    // Authenticated user actions
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Role management (protected)
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/users', [RoleController::class, 'usersWithRoles']);
    Route::post('/roles/assign/{user}', [RoleController::class, 'assignRole']);
    Route::post('/roles/remove/{user}', [RoleController::class, 'removeRole']);
});
