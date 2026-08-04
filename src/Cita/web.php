<?php

use Illuminate\Support\Facades\Route;
use Src\Cita\Application\Controllers\CitaWebController;

Route::middleware('auth')->group(function () {
    Route::get('citas', [CitaWebController::class, 'index'])->name('citas.index')->middleware('permission:citas.ver');
    Route::get('citas/calendario', [CitaWebController::class, 'calendario'])->name('citas.calendario')->middleware('permission:citas.ver');
    Route::get('citas/create', [CitaWebController::class, 'create'])->name('citas.create')->middleware('permission:citas.crear');
    Route::post('citas', [CitaWebController::class, 'store'])->name('citas.store')->middleware('permission:citas.crear');
    Route::patch('citas/{cita}/estado', [CitaWebController::class, 'cambiarEstado'])->name('citas.estado')->middleware('permission:citas.gestionar|citas.cancelar');
});
