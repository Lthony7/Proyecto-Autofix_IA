<?php
use Illuminate\Support\Facades\Route;
use Src\OrdenTrabajo\Application\Controllers\OrdenTrabajoWebController;
Route::middleware('auth')->group(function(){
    Route::get('ordenes',[OrdenTrabajoWebController::class,'index'])->name('ordenes.index')->middleware('permission:ordenes.ver');
    Route::get('ordenes/create',[OrdenTrabajoWebController::class,'create'])->name('ordenes.create')->middleware('permission:ordenes.crear');
    Route::post('ordenes',[OrdenTrabajoWebController::class,'store'])->name('ordenes.store')->middleware('permission:ordenes.crear');
    Route::post('citas/{cita}/orden',[OrdenTrabajoWebController::class,'convertirCita'])->name('citas.convertir-orden')->middleware('permission:ordenes.crear');
    Route::get('ordenes/{orden}',[OrdenTrabajoWebController::class,'show'])->name('ordenes.show')->middleware('permission:ordenes.ver');
    Route::patch('ordenes/{orden}/mecanicos',[OrdenTrabajoWebController::class,'asignar'])->name('ordenes.asignar')->middleware('permission:ordenes.asignar');
    Route::patch('ordenes/{orden}/estado',[OrdenTrabajoWebController::class,'cambiarEstado'])->name('ordenes.estado')->middleware('permission:ordenes.avanzar|ordenes.cancelar');
    Route::patch('ordenes/{orden}/servicios/{servicio}/estado',[OrdenTrabajoWebController::class,'cambiarEstadoServicio'])->name('ordenes.servicios.estado')->middleware('permission:ordenes.avanzar');
    Route::post('ordenes/{orden}/diagnosticos',[OrdenTrabajoWebController::class,'diagnosticar'])->name('ordenes.diagnosticar')->middleware('permission:diagnosticos.registrar');
});
