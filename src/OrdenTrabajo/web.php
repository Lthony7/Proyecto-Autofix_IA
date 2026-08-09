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
    Route::patch('ordenes/{orden}/estado',[OrdenTrabajoWebController::class,'cambiarEstado'])->name('ordenes.estado')->middleware('permission:ordenes.asignar|ordenes.actualizar_estado|ordenes.entregar|ordenes.cancelar');
    Route::patch('ordenes/{orden}/servicios/{servicio}/estado',[OrdenTrabajoWebController::class,'cambiarEstadoServicio'])->name('ordenes.servicios.estado')->middleware('permission:servicios.registrar');
    Route::post('ordenes/{orden}/servicios',[OrdenTrabajoWebController::class,'agregarServicio'])->name('ordenes.servicios.store')->middleware('permission:servicios.registrar');
    Route::patch('ordenes/{orden}/servicios/{servicio}/aprobacion',[OrdenTrabajoWebController::class,'aprobarServicio'])->name('ordenes.servicios.aprobacion')->middleware('permission:servicios.aprobar');
    Route::post('ordenes/{orden}/repuestos-requeridos',[OrdenTrabajoWebController::class,'agregarRepuestoRequerido'])->name('ordenes.repuestos-requeridos.store')->middleware('permission:repuestos.solicitar');
    Route::patch('ordenes/{orden}/repuestos-requeridos/{requerimiento}',[OrdenTrabajoWebController::class,'actualizarRepuestoRequerido'])->name('ordenes.repuestos-requeridos.update')->middleware('permission:repuestos.solicitar');
    Route::patch('ordenes/{orden}/repuestos-requeridos/{requerimiento}/estado',[OrdenTrabajoWebController::class,'cambiarEstadoRepuestoRequerido'])->name('ordenes.repuestos-requeridos.estado')->middleware('permission:repuestos.solicitar|repuestos.aprobar');
    Route::post('ordenes/{orden}/diagnosticos',[OrdenTrabajoWebController::class,'diagnosticar'])->name('ordenes.diagnosticar')->middleware(['permission:diagnosticos.crear|diagnosticos.editar|diagnosticos.corregir','permission:historial.tecnico.registrar']);
    Route::post('ordenes/{orden}/avances',[OrdenTrabajoWebController::class,'registrarAvance'])->name('ordenes.avances.store')->middleware('permission:avances.registrar');
    Route::patch('ordenes/{orden}/cierre-tecnico',[OrdenTrabajoWebController::class,'actualizarCierreTecnico'])->name('ordenes.cierre-tecnico')->middleware('permission:ordenes.cierre_tecnico');
});
