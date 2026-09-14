<?php

use App\Http\Controllers\AccessHistoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\SessionStatusController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:30,1')->name('login.store');
    Route::get('/access/code', [TwoFactorController::class, 'create'])->name('two-factor.create');
    Route::post('/access/code', [TwoFactorController::class, 'store'])->middleware('throttle:20,1')->name('two-factor.store');
    Route::post('/access/code/resend', [TwoFactorController::class, 'resend'])->middleware('throttle:5,1')->name('two-factor.resend');
    Route::post('/access/code/cancel', [TwoFactorController::class, 'cancel'])->name('two-factor.cancel');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/session/status', SessionStatusController::class)->withoutMiddleware(EnsureAccountActive::class)->name('session.status');
    Route::get('/access-history', AccessHistoryController::class)->middleware('can:view-access-history')->name('access.index');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'can:manage-users'])->group(function (): void {
    Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
    Route::resource('users', UserController::class)->except('destroy');
});

Route::middleware(['auth', 'can:view-operations'])->group(function (): void {
    Route::get('/contracts', [OperationController::class, 'contracts'])->name('contracts.index');
    Route::get('/payments', [OperationController::class, 'payments'])->name('payments.index');
    Route::get('/installations', [OperationController::class, 'installations'])->name('installations.index');
    Route::get('/equipment', [OperationController::class, 'equipment'])->name('equipment.index');
    Route::get('/materials', [OperationController::class, 'materials'])->name('materials.index');
});

Route::middleware(['auth', 'can:register-clients'])->group(function (): void {
    Route::patch('/clients/{client}/deactivate', [ClientController::class, 'deactivate'])->name('clients.deactivate');
    Route::resource('clients', ClientController::class)->except('destroy');
});
