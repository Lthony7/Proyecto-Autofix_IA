<?php

use Illuminate\Support\Facades\Route;
use Src\Taller\Application\Controllers\CatalogoTallerWebController;
use Src\Taller\Application\Controllers\MecanicoWebController;

Route::middleware('auth')->group(function () {
    Route::get('mecanicos', [MecanicoWebController::class, 'index'])->name('mecanicos.index')->middleware('permission:mecanicos.ver');
    Route::get('mecanicos/create', [MecanicoWebController::class, 'create'])->name('mecanicos.create')->middleware('permission:mecanicos.gestionar');
    Route::post('mecanicos', [MecanicoWebController::class, 'store'])->name('mecanicos.store')->middleware('permission:mecanicos.gestionar');
    Route::get('mecanicos/{mecanico}/edit', [MecanicoWebController::class, 'edit'])->name('mecanicos.edit')->middleware('permission:mecanicos.gestionar');
    Route::put('mecanicos/{mecanico}', [MecanicoWebController::class, 'update'])->name('mecanicos.update')->middleware('permission:mecanicos.gestionar');
    Route::patch('mecanicos/{mecanico}/estado', [MecanicoWebController::class, 'cambiarEstado'])->name('mecanicos.estado')->middleware('permission:mecanicos.gestionar');

    Route::get('taller/catalogos', [CatalogoTallerWebController::class, 'index'])->name('taller.catalogos')->middleware('permission:mecanicos.ver|especialidades.gestionar|servicios.gestionar');
    Route::post('taller/especialidades', [CatalogoTallerWebController::class, 'storeEspecialidad'])->name('especialidades.store')->middleware('permission:especialidades.gestionar');
    Route::patch('taller/especialidades/{especialidad}/estado', [CatalogoTallerWebController::class, 'estadoEspecialidad'])->name('especialidades.estado')->middleware('permission:especialidades.gestionar');
    Route::post('taller/servicios', [CatalogoTallerWebController::class, 'storeServicio'])->name('servicios.store')->middleware('permission:servicios.gestionar');
    Route::patch('taller/servicios/{servicio}/estado', [CatalogoTallerWebController::class, 'estadoServicio'])->name('servicios.estado')->middleware('permission:servicios.gestionar');
});
