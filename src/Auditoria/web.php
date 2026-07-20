<?php
use Illuminate\Support\Facades\Route;use Src\Auditoria\Application\Controllers\AuditoriaWebController;
Route::middleware('auth')->group(function(){Route::get('auditorias',[AuditoriaWebController::class,'index'])->name('auditorias.index')->middleware('permission:auditorias.ver');});
