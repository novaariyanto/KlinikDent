<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/users/data', [UserController::class, 'data'])
        ->middleware('permission:users.view')
        ->name('users.data');
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
        ->middleware('permission:users.edit')
        ->name('users.toggle-status');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->middleware('permission:users.edit')
        ->name('users.reset-password');
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])
        ->middleware('permission:users.impersonate')
        ->name('users.impersonate');
    Route::post('/impersonate/leave', [UserController::class, 'leaveImpersonate'])
        ->name('impersonate.leave');
    Route::resource('users', UserController::class);

    Route::get('/roles/data', [RoleController::class, 'data'])
        ->middleware('permission:roles.view')
        ->name('roles.data');
    Route::resource('roles', RoleController::class)->except(['show']);

    Route::get('/menus/data', [MenuController::class, 'data'])
        ->middleware('permission:menus.view')
        ->name('menus.data');
    Route::post('/menus/{menu}/toggle-status', [MenuController::class, 'toggleStatus'])
        ->middleware('permission:menus.edit')
        ->name('menus.toggle-status');
    Route::resource('menus', MenuController::class)->except(['show']);

    Route::middleware('permission:settings.update')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');
    });
});
