<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ErrorEventController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware(['guest', 'throttle:login'])
    ->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'user'])->name('user');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::apiResource('customers', CustomerController::class);
    Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
    Route::apiResource('orders', OrderController::class);
    Route::post('/invoices/{invoice}/email', [InvoiceController::class, 'email'])->middleware('throttle:invoice-email')->name('invoices.email');
    Route::post('/invoices/{invoice}/reopen', [InvoiceController::class, 'reopen'])->name('invoices.reopen');
    Route::apiResource('invoices', InvoiceController::class);

    Route::post('/error-events/trigger', [ErrorEventController::class, 'trigger'])
        ->middleware('local.only')
        ->name('error-events.trigger');
    Route::apiResource('error-events', ErrorEventController::class)->only(['index', 'show', 'update']);

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
});
