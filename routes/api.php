<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']); 
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']); // Logout
});

Route::middleware('auth:sanctum')->prefix('transactions')->group(function () {
    Route::post('/deposit', [TransactionController::class, 'deposit']); // Depósito
    Route::post('/transfer', [TransactionController::class, 'transfer']); // Transferência
    Route::post('/reverse', [TransactionController::class, 'reverse']); // Reversão
    Route::get('/history', [TransactionController::class, 'history']); // Histórico
});
