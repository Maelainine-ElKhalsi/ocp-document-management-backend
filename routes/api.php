<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\AxeController;
use App\Http\Controllers\Api\DossierController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\UserController;

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::apiResource('services', ServiceController::class);
    Route::apiResource('axes',     AxeController::class);
    Route::apiResource('dossiers', DossierController::class);
    Route::apiResource('files',    FileController::class);

    Route::get('/files/{id}/view', [FileController::class, 'view']);
    Route::get('/files/{id}/download', [FileController::class, 'download']);

    Route::get('/audit-logs/latest', [AuditLogController::class, 'latest']);
    Route::get('/audit-logs/users/{id}', [AuditLogController::class, 'latestByUser']);

    // Users — Admin only
    Route::get('/users/search', [UserController::class, 'search']);
    Route::get('/users',        [UserController::class, 'index']);
    Route::post('/users',       [UserController::class, 'store']);
    Route::put('/users/{id}',   [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
