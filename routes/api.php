<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\TeamController;
use App\Http\Controllers\API\ForgotPasswordController;

// Public authentication routes
Route::get('/departments', [DepartmentController::class, 'index']);
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
    ->middleware('throttle:auth-forgot-password');
Route::post('/auth/reset-password', [ForgotPasswordController::class, 'resetPassword'])
    ->middleware('throttle:auth-reset-password');

// Protected routes (requires auth:sanctum)
Route::middleware(['auth:sanctum', 'active_user'])->group(function () {
    // Authenticated user actions
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/team/users', [TeamController::class, 'users']);
    Route::get('/team/subordinates', [TeamController::class, 'subordinates']);
    Route::post('/team/subordinates', [TeamController::class, 'assignSubordinate']);
    Route::delete('/team/subordinates/{subordinate}', [TeamController::class, 'removeSubordinate']);
    Route::apiResource('tasks', TaskController::class);
    Route::get('/system/announcements', [AdminController::class, 'activeAnnouncements']);
    Route::get('/system/theme', [AdminController::class, 'activeTheme']);

    // Admin-only role management (RBAC enforced)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/admin/summary', [AdminController::class, 'summary']);
        Route::get('/admin/users', [AdminController::class, 'users']);
        Route::post('/admin/users', [AdminController::class, 'createUser']);
        Route::patch('/admin/users/{user}/role', [AdminController::class, 'updateUserRole']);
        Route::patch('/admin/users/{user}/suspend', [AdminController::class, 'suspendUser']);
        Route::patch('/admin/users/{user}/reactivate', [AdminController::class, 'reactivateUser']);
        Route::delete('/admin/users/{user}', [AdminController::class, 'destroyUser']);
        Route::get('/admin/announcements', [AdminController::class, 'announcements']);
        Route::post('/admin/announcements', [AdminController::class, 'createAnnouncement']);
        Route::delete('/admin/announcements/{announcement}', [AdminController::class, 'deleteAnnouncement']);
        Route::get('/admin/themes', [AdminController::class, 'themes']);
        Route::post('/admin/themes', [AdminController::class, 'createTheme']);
        Route::patch('/admin/themes/{theme}/activate', [AdminController::class, 'activateTheme']);

        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/users', [RoleController::class, 'usersWithRoles']);
        Route::post('/roles/assign/{user}', [RoleController::class, 'assignRole']);
        Route::post('/roles/remove/{user}', [RoleController::class, 'removeRole']);
    });
});
