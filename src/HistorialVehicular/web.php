<?php

use Illuminate\Support\Facades\Route;
use Src\HistorialVehicular\Application\Controllers\HistorialVehicularWebController;

Route::middleware(['auth', 'permission:historial.ver'])->group(function () {
    Route::get('historial-vehicular', [HistorialVehicularWebController::class, 'index'])
        ->name('historial-vehicular.index');
    Route::get('historial-vehicular/{vehiculo}', [HistorialVehicularWebController::class, 'show'])
        ->name('historial-vehicular.show');
});
