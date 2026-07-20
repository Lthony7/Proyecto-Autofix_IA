<?php

use Illuminate\Support\Facades\Route;
use Src\Cliente\Application\Controllers\ClienteWebController;

Route::middleware('auth')->group(function () {
    Route::resource('clientes', ClienteWebController::class)
        ->only(['index'])
        ->middleware('permission:clientes.ver');
    Route::resource('clientes', ClienteWebController::class)
        ->only(['create', 'store'])
        ->middleware('permission:clientes.crear');
    Route::resource('clientes', ClienteWebController::class)
        ->only(['edit', 'update'])
        ->middleware('permission:clientes.editar');
    Route::patch('clientes/{cliente}/estado', [ClienteWebController::class, 'cambiarEstado'])
        ->name('clientes.estado')
        ->middleware('permission:clientes.desactivar');
});
