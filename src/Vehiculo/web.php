<?php

use Illuminate\Support\Facades\Route;
use Src\Vehiculo\Application\Controllers\VehiculoWebController;

Route::middleware('auth')->group(function () {
    Route::get('vehiculos', [VehiculoWebController::class, 'index'])
        ->name('vehiculos.index')->middleware('permission:vehiculos.ver');
    Route::get('vehiculos/create', [VehiculoWebController::class, 'create'])
        ->name('vehiculos.create')->middleware('permission:vehiculos.crear');
    Route::post('vehiculos', [VehiculoWebController::class, 'store'])
        ->name('vehiculos.store')->middleware('permission:vehiculos.crear');
    Route::get('vehiculos/{vehiculo}/edit', [VehiculoWebController::class, 'edit'])
        ->name('vehiculos.edit')->middleware('permission:vehiculos.editar');
    Route::put('vehiculos/{vehiculo}', [VehiculoWebController::class, 'update'])
        ->name('vehiculos.update')->middleware('permission:vehiculos.editar');
    Route::patch('vehiculos/{vehiculo}/estado', [VehiculoWebController::class, 'cambiarEstado'])
        ->name('vehiculos.estado')->middleware('permission:vehiculos.desactivar');
});
