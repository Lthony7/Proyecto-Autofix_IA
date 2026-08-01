<?php

use Illuminate\Support\Facades\Route;
use Src\HistorialVehicular\Application\Controllers\HistorialVehicularWebController;

Route::middleware(['auth', 'permission:historial.servicios.ver'])->group(function () {
    Route::get('mi-historial', [HistorialVehicularWebController::class, 'index'])
        ->defaults('modo', 'cliente')->name('mi-historial.index');
    Route::get('mi-historial/{vehiculo}', [HistorialVehicularWebController::class, 'show'])
        ->defaults('modo', 'cliente')->name('mi-historial.show');
    Route::get('historial-vehicular', [HistorialVehicularWebController::class, 'index'])
        ->name('historial-vehicular.index');
    Route::get('historial-vehicular-bitacora', [HistorialVehicularWebController::class, 'bitacora'])
        ->middleware('permission:historial.acciones.ver')->name('historial-vehicular.bitacora');
    Route::get('historial-vehicular/{vehiculo}', [HistorialVehicularWebController::class, 'show'])
        ->name('historial-vehicular.show');
});
