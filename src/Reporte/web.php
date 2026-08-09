<?php

use Illuminate\Support\Facades\Route;
use Src\Reporte\Application\Controllers\ReporteWebController;

Route::middleware('auth')->group(function () {
    Route::get('reportes', [ReporteWebController::class, 'index'])
        ->name('reportes.index')->middleware('permission:reportes.ver');
    Route::get('reportes/filtros', [ReporteWebController::class, 'index'])->defaults('vista', 'filtros')->name('reportes.filtros')->middleware('permission:reportes.ver');
    Route::get('reportes/ordenes-pendientes', [ReporteWebController::class, 'index'])->defaults('vista', 'ordenes-pendientes')->name('reportes.ordenes-pendientes')->middleware('permission:reportes.ver');
    Route::get('reportes/ordenes-en-reparacion', [ReporteWebController::class, 'index'])->defaults('vista', 'ordenes-en-reparacion')->name('reportes.ordenes-en-reparacion')->middleware('permission:reportes.ver');
    Route::get('reportes/ordenes-finalizadas', [ReporteWebController::class, 'index'])->defaults('vista', 'ordenes-finalizadas')->name('reportes.ordenes-finalizadas')->middleware('permission:reportes.ver');
    Route::get('reportes/ingresos', [ReporteWebController::class, 'index'])->defaults('vista', 'ingresos')->name('reportes.ingresos')->middleware(['permission:reportes.ver', 'permission:reportes.financieros']);
    Route::get('reportes/servicios', [ReporteWebController::class, 'index'])->defaults('vista', 'servicios')->name('reportes.servicios')->middleware('permission:reportes.ver');
    Route::get('reportes/repuestos', [ReporteWebController::class, 'index'])->defaults('vista', 'repuestos')->name('reportes.repuestos')->middleware('permission:reportes.ver');
    Route::get('reportes/vehiculos-clientes', [ReporteWebController::class, 'index'])->defaults('vista', 'vehiculos-clientes')->name('reportes.vehiculos-clientes')->middleware('permission:reportes.ver');
    Route::get('reportes/exportar', [ReporteWebController::class, 'exportar'])
        ->name('reportes.exportar')->middleware(['permission:reportes.ver', 'permission:reportes.exportar']);
});
