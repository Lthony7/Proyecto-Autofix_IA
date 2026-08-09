<?php

use Illuminate\Support\Facades\Route;
use Src\Auth\Application\Controllers\PasswordResetController;
use Src\Auth\Application\Controllers\WebAuthController;
use Src\Auth\Application\Controllers\UserWebController;

Route::middleware('guest')->group(function () {
    Route::view('/', 'welcome');

    Route::get('/login', [WebAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::get('/register', [WebAuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [WebAuthController::class, 'register'])
        ->middleware('throttle:3,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    Route::get('/usuarios', [UserWebController::class, 'index'])->name('usuarios.index')->middleware('permission:usuarios.ver');
    Route::get('/usuarios/create', [UserWebController::class, 'create'])->name('usuarios.create')->middleware('permission:usuarios.crear');
    Route::post('/usuarios', [UserWebController::class, 'store'])->name('usuarios.store')->middleware('permission:usuarios.crear');
    Route::get('/usuarios/{usuario}/edit', [UserWebController::class, 'edit'])->name('usuarios.edit')->middleware('permission:usuarios.editar');
    Route::put('/usuarios/{usuario}', [UserWebController::class, 'update'])->name('usuarios.update')->middleware('permission:usuarios.editar');
    Route::patch('/usuarios/{usuario}/roles', [UserWebController::class, 'roles'])->name('usuarios.roles')->middleware('permission:roles.administrar');
    Route::patch('/usuarios/{usuario}/estado', [UserWebController::class, 'estado'])->name('usuarios.estado')->middleware('permission:usuarios.desactivar');
    Route::delete('/usuarios/{usuario}', [UserWebController::class, 'eliminar'])->name('usuarios.eliminar')->middleware('permission:usuarios.eliminar');
});
