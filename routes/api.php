<?php

use App\Http\Controllers\AuthController;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/signup', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/me', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('categories', fn() => Category::orderBy('name')->get());
    Route::apiResource('expenses', \App\Http\Controllers\ExpenseController::class);
});
