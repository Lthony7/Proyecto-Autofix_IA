<?php
use Illuminate\Support\Facades\Route;use Src\Pago\Application\Controllers\PagoWebController;
Route::middleware('auth')->group(function(){Route::get('pagos',[PagoWebController::class,'index'])->name('pagos.index')->middleware('permission:pagos.ver');Route::post('ordenes/{orden}/pagos',[PagoWebController::class,'store'])->name('pagos.store')->middleware('permission:pagos.registrar');Route::post('pagos/{pago}/anular',[PagoWebController::class,'anular'])->name('pagos.anular')->middleware('permission:pagos.anular');});
