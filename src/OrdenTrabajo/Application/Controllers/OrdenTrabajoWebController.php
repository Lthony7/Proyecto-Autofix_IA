<?php

namespace Src\OrdenTrabajo\Application\Controllers;

use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel;
use Src\AsistenteIA\Infrastructure\Models\RevisionSugerenciaIaEloquentModel;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Inventario\Infrastructure\Models\OrdenRepuestoEloquentModel;
use Src\Inventario\Infrastructure\Models\RepuestoEloquentModel;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;
use Src\HistorialVehicular\Application\Services\RegistrarEventoVehiculo;
use Src\OrdenTrabajo\Application\Services\ValidarPreparacionTrabajo;
use Src\OrdenTrabajo\Application\Services\AutorizarMecanicoOrden;
use Src\OrdenTrabajo\Application\Services\CerrarRecursosOrdenCancelada;
use Src\OrdenTrabajo\Application\Services\FlujoEstadosOrden;
use Src\OrdenTrabajo\Infrastructure\Models\DiagnosticoTecnicoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenAvanceEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenEstadoHistorialEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenMecanicoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenRepuestoRequeridoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenServicioEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Requests\AsignarMecanicosRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\CambiarEstadoOrdenRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\GuardarOrdenRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\RegistrarDiagnosticoRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\RegistrarAvanceRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\ActualizarCierreTecnicoRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\GuardarRepuestoRequeridoRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\ActualizarRepuestoRequeridoRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\CambiarEstadoRepuestoRequeridoRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\GuardarServicioOrdenRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\CambiarEstadoServicioOrdenRequest;
use Src\OrdenTrabajo\Infrastructure\Requests\AprobarServicioOrdenRequest;
use Src\Pago\Application\Services\CalculadorTotalOrden;
use Src\Pago\Infrastructure\Models\PagoEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;

class OrdenTrabajoWebController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::in(['pendiente', ...FlujoEstadosOrden::DESTINOS])]]);
        $conteos = OrdenTrabajoEloquentModel::query()
            ->visiblePara($request->user())
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $resumenEstados = [
            'cerradas' => (int) ($conteos['finalizada'] ?? 0) + (int) ($conteos['lista_entrega'] ?? 0) + (int) ($conteos['entregada'] ?? 0),
            'enCurso' => collect(['asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba'])->sum(fn ($estado) => (int) ($conteos[$estado] ?? 0)),
            'pendientes' => (int) ($conteos['pendiente'] ?? 0),
        ];

        $estado=$filtros['estado'] ?? null; $ordenes=OrdenTrabajoEloquentModel::with(['cliente:id,razon_social','vehiculo:id,placa,marca,modelo','asignaciones'=>fn($q)=>$q->where('activo',true)->with('mecanico:id,nombres,apellidos')])->visiblePara($request->user())->when($estado,fn($q)=>$q->where('estado',$estado))->latest('recibida_en')->paginate(10)->withQueryString();
        $ordenes->through(fn($o)=>$this->resumen($o)); return Inertia::render('OrdenTrabajo/index',['ordenes'=>$ordenes,'estado'=>$estado,'resumenEstados'=>$resumenEstados]);
    }

    public function create(): Response { return Inertia::render('OrdenTrabajo/form',$this->catalogos()); }

    public function store(GuardarOrdenRequest $request, RegistrarAuditoria $auditoria, RegistrarEventoVehiculo $historial): RedirectResponse
    {
        $orden=DB::transaction(function()use($request){$d=$request->validated();$orden=$this->crearOrden($d['cliente_id'],$d['vehiculo_id'],$d['falla_reportada'],$d['kilometraje']??null,$d['servicio_ids'],$d['mecanico_ids']??[],$request->user()->id);return $orden;});
        $auditoria->registrar('orden.creada','orden_trabajo',$orden->id,[],$request);$historial->registrar($orden->vehiculo_id,'orden.creada',"Se creó la orden de trabajo {$orden->numero}.",['orden_id'=>$orden->id,'numero'=>$orden->numero],$request);return redirect()->route('ordenes.show',$orden)->with('success','Orden de trabajo creada.');
    }

    public function convertirCita(Request $request,CitaEloquentModel $cita,RegistrarAuditoria $auditoria,RegistrarEventoVehiculo $historial): RedirectResponse
    {
        abort_unless($request->user()->can('ordenes.crear'),403);
        [$orden,$creada]=DB::transaction(function()use($cita,$request){$bloqueada=CitaEloquentModel::whereKey($cita->id)->lockForUpdate()->firstOrFail();$existente=OrdenTrabajoEloquentModel::where('cita_id',$bloqueada->id)->first();if($existente){\Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel::where('cita_id',$bloqueada->id)->update(['orden_id'=>$existente->id]);return[$existente,false];}if($bloqueada->estado!=='atendida')throw ValidationException::withMessages(['cita'=>'Solo una cita atendida puede convertirse en orden.']);$servicios=$bloqueada->servicio_id?[$bloqueada->servicio_id]:[];if(!$servicios)throw ValidationException::withMessages(['cita'=>'La cita debe tener un servicio antes de crear la orden.']);$mecanicos=$bloqueada->mecanico_id?[$bloqueada->mecanico_id]:[];$orden=$this->crearOrden($bloqueada->cliente_id,$bloqueada->vehiculo_id,$bloqueada->motivo,$bloqueada->kilometraje,$servicios,$mecanicos,$request->user()->id);$orden->update(['cita_id'=>$bloqueada->id]);\Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel::where('cita_id',$bloqueada->id)->update(['orden_id'=>$orden->id]);return[$orden,true];});
        if($creada){$auditoria->registrar('cita.convertida_orden','orden_trabajo',$orden->id,['cita_id'=>$cita->id],$request);$historial->registrar($orden->vehiculo_id,'orden.creada',"Se creó la orden {$orden->numero} desde una cita.",['orden_id'=>$orden->id,'cita_id'=>$cita->id],$request);}return redirect()->route('ordenes.show',$orden)->with('success',$creada?'Orden creada desde la cita.':'La cita ya tenía una orden; se abrió la existente.');
    }

    public function show(Request $request,OrdenTrabajoEloquentModel $orden,CalculadorTotalOrden $calculador): Response
    {
        $this->autorizarVista($request,$orden);$orden->load(['cliente','vehiculo','servicios','asignaciones'=>fn($q)=>$q->where('activo',true)->with('mecanico'),'diagnosticos'=>fn($q)=>$q->with('autor:id,name')->orderByDesc('version'),'cita'=>fn($q)=>$q->with(['especialidad:id,nombre','servicio:id,nombre','mecanico:id,nombres,apellidos','repuestosSolicitados','historial'=>fn($h)=>$h->latest('created_at')])]);
        $esCliente=$request->user()->cliente()->whereKey($orden->cliente_id)->exists()&&!$request->user()->hasAnyPermission(['ordenes.administrar','ordenes.crear','ordenes.asignar','ordenes.ver_asignadas']);$puedeTrabajo=app(AutorizarMecanicoOrden::class)->permite($request->user(),$orden);$puedeVerFinanzas=$request->user()->can('pagos.ver');
        $catalogoRepuestos=$puedeTrabajo?RepuestoEloquentModel::where('estado','activo')->orderBy('nombre')->get(['id','codigo','nombre','unidad','stock_actual','precio_venta']):collect();
        $repuestos=$puedeTrabajo&&$request->user()->can('repuestos.utilizar')?$catalogoRepuestos->map(fn($p)=>['label'=>"{$p->codigo} · {$p->nombre} · stock {$p->stock_actual} {$p->unidad}",'value'=>$p->id]):[];
        $usos=OrdenRepuestoEloquentModel::with('repuesto:id,codigo,nombre,unidad')->where('orden_id',$orden->id)->when($esCliente,fn($q)=>$q->where('visible_cliente',true))->latest('created_at')->get()->map(fn($u)=>['id'=>$u->id,'codigo'=>$u->codigo_snapshot?:$u->repuesto?->codigo,'nombre'=>$u->nombre_snapshot?:$u->repuesto?->nombre,'unidad'=>$u->unidad_snapshot?:$u->repuesto?->unidad,'cantidad'=>$u->cantidad,'fuenteSuministro'=>$u->fuente_suministro,'facturable'=>$u->facturable,'precioUnitario'=>$puedeVerFinanzas?$u->precio_unitario:null,'revertido'=>$u->revertido_en!==null,'createdAt'=>$u->created_at?->toIso8601String()]);
        $cantidadesUsadas=OrdenRepuestoEloquentModel::where('orden_id',$orden->id)->whereNull('revertido_en')->whereNotNull('requerimiento_id')->selectRaw('requerimiento_id, SUM(cantidad) AS total')->groupBy('requerimiento_id')->pluck('total','requerimiento_id');
        $requerimientos=$esCliente?collect():OrdenRepuestoRequeridoEloquentModel::where('orden_id',$orden->id)->latest()->get()->map(function($r)use($catalogoRepuestos,$cantidadesUsadas,$puedeVerFinanzas){$p=$catalogoRepuestos->firstWhere('id',$r->repuesto_id);$usada=(float)($cantidadesUsadas[$r->id]??0);$restante=max(0,(float)$r->cantidad-$usada);return['id'=>$r->id,'repuestoId'=>$r->repuesto_id,'origen'=>$r->origen,'descripcion'=>$r->descripcion,'cantidad'=>$r->cantidad,'cantidadUsada'=>number_format($usada,3,'.',''),'cantidadRestante'=>number_format($restante,3,'.',''),'cumplimiento'=>$restante<=0?'completo':($usada>0?'parcial':'pendiente'),'motivo'=>$r->motivo,'estado'=>$r->estado,'prioridad'=>$r->prioridad,'obligatorio'=>$r->obligatorio,'fuenteSuministro'=>$r->fuente_suministro,'stock'=>$p?->stock_actual,'unidad'=>$r->unidad_snapshot?:$p?->unidad,'precio'=>$puedeVerFinanzas?$r->precio_unitario_aprobado:null,'disponible'=>$r->fuente_suministro!=='inventario'||($p&&(float)$p->stock_actual>=$restante)];});
        $finanzas=$puedeVerFinanzas?$calculador->calcular($orden->id):null;$pagos=$puedeVerFinanzas?PagoEloquentModel::where('orden_id',$orden->id)->latest('pagado_en')->get()->map(fn($p)=>['id'=>$p->id,'numero'=>$p->numero,'comprobante'=>$p->comprobante_numero,'monto'=>$p->monto,'metodo'=>$p->metodo,'referencia'=>$p->referencia,'estado'=>$p->estado,'pagadoEn'=>$p->pagado_en->toIso8601String(),'motivoAnulacion'=>$p->motivo_anulacion,'motivoReembolso'=>$p->motivo_reembolso]):[];
        $factura=($request->user()->can('facturas.ver')||$request->user()->can('facturas.crear'))?FacturaOrdenEloquentModel::where('orden_id',$orden->id)->where('estado','emitida')->first(['id','numero','total','emitida_en']):null;
        $consultaIa=\Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel::with(['especialidad:id,nombre','mecanicoSugerido:id,nombres,apellidos','revisiones'])->where('orden_id',$orden->id)->latest()->first();
        $respuestaIa=$consultaIa?->revisiones->where('estado_nuevo','modificada')->last()?->respuesta_ajustada??$consultaIa?->respuesta_original;
        $revisionIa=$consultaIa?->revisiones->last();
        $avances=OrdenAvanceEloquentModel::with(['autor:id,name','servicio:id,nombre_servicio'])->where('orden_id',$orden->id)->when($esCliente,fn($q)=>$q->where('visibilidad','cliente'))->latest('created_at')->get()->map(fn($avance)=>['id'=>$avance->id,'tipo'=>$avance->tipo,'descripcion'=>$avance->descripcion,'visibilidad'=>$avance->visibilidad,'estadoOrden'=>$avance->estado_orden,'porcentaje'=>$avance->porcentaje,'fechaEstimadaFinalizacion'=>$avance->fecha_estimada_finalizacion?->toIso8601String(),'notaInterna'=>$esCliente?null:$avance->nota_interna,'autor'=>$esCliente?'Taller':$avance->autor?->name,'servicio'=>$avance->servicio?->nombre_servicio,'createdAt'=>$avance->created_at?->toIso8601String()]);
        $cita=$orden->cita?['id'=>$orden->cita->id,'numero'=>$orden->cita->numero,'estado'=>$orden->cita->estado,'inicio'=>$orden->cita->inicio->toIso8601String(),'fin'=>$orden->cita->fin->toIso8601String(),'atendidaEn'=>$orden->cita->atendida_en?->toIso8601String(),'motivo'=>$orden->cita->motivo,'kilometraje'=>$orden->cita->kilometraje,'especialidad'=>$orden->cita->especialidad?->nombre,'servicioSolicitado'=>$orden->cita->servicio?->nombre,'mecanicoSolicitado'=>$orden->cita->mecanico?trim("{$orden->cita->mecanico->nombres} {$orden->cita->mecanico->apellidos}"):null,'repuestosSolicitados'=>$esCliente?[]:$orden->cita->repuestosSolicitados->map(fn($r)=>['descripcion'=>$r->descripcion,'cantidad'=>$r->cantidad,'observaciones'=>$r->observaciones]),'historial'=>$esCliente?[]:$orden->cita->historial->map(fn($h)=>['estadoAnterior'=>$h->estado_anterior,'estadoNuevo'=>$h->estado_nuevo,'observaciones'=>$h->observaciones,'fecha'=>$h->created_at?->toIso8601String()])]:null;
        $diagnosticoIa=$esCliente?null:($consultaIa?['id'=>$consultaIa->id,'estado'=>$consultaIa->estado,'entrada'=>$consultaIa->entrada,'respuesta'=>$consultaIa->estado==='descartada'?null:$respuestaIa,'especialidad'=>$consultaIa->especialidad?->nombre,'mecanico'=>$consultaIa->mecanicoSugerido?trim("{$consultaIa->mecanicoSugerido->nombres} {$consultaIa->mecanicoSugerido->apellidos}"):null,'revisada'=>in_array($consultaIa->estado,['confirmada','modificada'],true),'revision'=>$revisionIa?['estado'=>$revisionIa->estado_nuevo,'observacionesCliente'=>$revisionIa->observaciones_cliente?:$revisionIa->observaciones,'motivoDiferencia'=>$revisionIa->motivo_diferencia,'pruebasRealizadas'=>collect($revisionIa->pruebas_realizadas??[])->pluck('descripcion')->filter()->values(),'notasInternas'=>$request->user()->can('ia.revisar')?$revisionIa->notas_internas:null,'fecha'=>$revisionIa->created_at?->toIso8601String()]:null]:null);
        $estadosTecnicos=['asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba'];$puedeAccionTecnica=$request->user()->can('avances.registrar')||$request->user()->can('servicios.registrar')||$request->user()->can('repuestos.solicitar');$puedeDiagnostico=$request->user()->can('diagnosticos.crear')||$request->user()->can('diagnosticos.editar');$puedeCorregirCerrado=$request->user()->can('correctDiagnosis',$orden);$puedeMutar=$request->user()->can('mutate',$orden);$capacidades=['trabajo'=>$puedeTrabajo&&$puedeAccionTecnica,'diagnosticar'=>($puedeTrabajo&&$puedeDiagnostico)||$puedeCorregirCerrado,'consumir'=>$puedeTrabajo&&$request->user()->can('repuestos.utilizar')&&in_array($orden->estado,['en_diagnostico','esperando_repuestos','en_reparacion'],true),'revertirConsumo'=>$puedeTrabajo&&$request->user()->can('repuestos.utilizar'),'avanzarEstado'=>$puedeTrabajo&&$request->user()->can('ordenes.actualizar_estado'),'entregar'=>$request->user()->can('deliver',$orden),'cancelar'=>$request->user()->can('cancel',$orden),'asignar'=>$request->user()->can('assign',$orden),'aprobarRepuestos'=>$puedeMutar&&$request->user()->can('repuestos.aprobar'),'aprobarServicios'=>$puedeMutar&&$request->user()->can('servicios.aprobar'),'cerrarTecnico'=>$puedeTrabajo&&$request->user()->can('ordenes.cierre_tecnico')&&in_array($orden->estado,$estadosTecnicos,true),'verFinanzas'=>$puedeVerFinanzas];
        $historialEstados=DB::table('orden_estado_historial as historial')->leftJoin('users as usuario','usuario.id','=','historial.usuario_id')->where('historial.orden_id',$orden->id)->orderByDesc('historial.created_at')->get(['historial.estado_anterior','historial.estado_nuevo','historial.observaciones','historial.created_at','usuario.name as autor'])->map(fn($h)=>['estadoAnterior'=>$h->estado_anterior,'estadoNuevo'=>$h->estado_nuevo,'observaciones'=>$h->observaciones,'createdAt'=>$h->created_at,'autor'=>$esCliente?'Taller':$h->autor]);
        return Inertia::render('OrdenTrabajo/show',['orden'=>$this->detalle($orden,$esCliente,$puedeVerFinanzas),'cita'=>$cita,'capacidades'=>$capacidades,'esCliente'=>$esCliente,'mecanicos'=>$capacidades['asignar']?MecanicoEloquentModel::where('estado','activo')->orderBy('apellidos')->get()->map(fn($m)=>['label'=>"{$m->nombres} {$m->apellidos}",'value'=>$m->id]):[],'serviciosCatalogo'=>$puedeTrabajo?ServicioEloquentModel::where('estado','activo')->orderBy('nombre')->get()->map(fn($s)=>['label'=>$puedeVerFinanzas?"{$s->nombre} · $ {$s->precio_base}":$s->nombre,'value'=>$s->id]):[],'repuestosCatalogo'=>$catalogoRepuestos->map(fn($p)=>['id'=>$p->id,'label'=>"{$p->codigo} · {$p->nombre}",'stock'=>$p->stock_actual,'unidad'=>$p->unidad,'precio'=>$puedeVerFinanzas?$p->precio_venta:null]),'repuestos'=>$repuestos,'repuestosRequeridos'=>$requerimientos,'repuestosUsados'=>$usos,'avances'=>$avances,'historialEstados'=>$historialEstados,'finanzas'=>$finanzas,'pagos'=>$pagos,'factura'=>$factura?['id'=>$factura->id,'numero'=>$factura->numero,'total'=>$factura->total,'emitidaEn'=>$factura->emitida_en->toIso8601String()]:null,'configuracionFinanciera'=>['tasaImpuesto'=>(string)config('autofix.tax_rate','0.00')],'diagnosticoIa'=>$diagnosticoIa]);
    }

    public function asignar(AsignarMecanicosRequest $request,OrdenTrabajoEloquentModel $orden,RegistrarAuditoria $auditoria,RegistrarEventoVehiculo $historial): RedirectResponse
    {
        DB::transaction(function()use($request,$orden){$bloqueada=OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();abort_unless($request->user()->can('assign',$bloqueada),403);$bloqueada->asignaciones()->where('activo',true)->update(['activo'=>false,'retirado_en'=>now()]);foreach($request->validated('mecanico_ids')as$id)OrdenMecanicoEloquentModel::create(['orden_id'=>$bloqueada->id,'mecanico_id'=>$id,'activo'=>true,'asignado_por'=>$request->user()->id,'observaciones'=>$request->validated('observaciones')]);});
        $mecanicos=MecanicoEloquentModel::whereIn('id',$request->validated('mecanico_ids'))->get()->map(fn($m)=>trim("{$m->nombres} {$m->apellidos}"))->values()->all();$auditoria->registrar('orden.mecanicos_asignados','orden_trabajo',$orden->id,['mecanicos'=>$request->validated('mecanico_ids')],$request);$historial->registrar($orden->vehiculo_id,'orden.mecanicos_asignados',"Se actualizaron los mecánicos de la orden {$orden->numero}.",['mecanicos'=>$mecanicos,'orden_id'=>$orden->id],$request);return back()->with('success','Mecánicos asignados.');
    }

    public function cambiarEstado(CambiarEstadoOrdenRequest $request,OrdenTrabajoEloquentModel $orden,CalculadorTotalOrden $calculador,ValidarPreparacionTrabajo $preparacion,FlujoEstadosOrden $flujo,CerrarRecursosOrdenCancelada $cerrarCancelada,RegistrarAuditoria $auditoria,RegistrarEventoVehiculo $historial): RedirectResponse
    {
        return $this->procesarCambioEstado($request, $orden, $calculador, $preparacion, $flujo, $cerrarCancelada, $auditoria, $historial);
        /* Implementación histórica pendiente de retirada física; no se ejecuta.
        DB::transaction(function()use($request,$orden,$nuevo,$calculador){$bloqueada=OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();$map=['pendiente'=>['en_diagnostico','cancelada'],'en_diagnostico'=>['en_reparacion','cancelada'],'en_reparacion'=>['finalizada','cancelada'],'finalizada'=>['entregada'],'entregada'=>[],'cancelada'=>[]];if(!in_array($nuevo,$map[$bloqueada->estado]??[],true))throw ValidationException::withMessages(['estado'=>"Transición inválida de {$bloqueada->estado} a {$nuevo}."]);if($nuevo==='finalizada'){if(OrdenServicioEloquentModel::where('orden_id',$bloqueada->id)->whereIn('estado',['pendiente','en_proceso'])->exists())throw ValidationException::withMessages(['estado'=>'Completa o cancela todos los servicios antes de finalizar la orden.']);$servicios=OrdenServicioEloquentModel::where('orden_id',$bloqueada->id)->where('estado','completado')->exists();$repuestos=OrdenRepuestoEloquentModel::where('orden_id',$bloqueada->id)->whereNull('revertido_en')->exists();if(!$servicios&&!$repuestos)throw ValidationException::withMessages(['estado'=>'La orden debe conservar al menos un servicio completado o repuesto utilizado.']);}if($nuevo==='cancelada'){if(PagoEloquentModel::where('orden_id',$bloqueada->id)->where('estado','registrado')->exists())throw ValidationException::withMessages(['estado'=>'Primero anula los pagos registrados antes de cancelar la orden.']);if(OrdenRepuestoEloquentModel::where('orden_id',$bloqueada->id)->whereNull('revertido_en')->exists())throw ValidationException::withMessages(['estado'=>'Primero revierte los repuestos utilizados antes de cancelar la orden.']);}if($nuevo==='entregada'){if(!FacturaOrdenEloquentModel::where('orden_id',$bloqueada->id)->where('estado','emitida')->exists())throw ValidationException::withMessages(['estado'=>'Emite la factura definitiva antes de entregar el vehículo.']);$resumen=$calculador->calcular($bloqueada->id);if(!BigDecimal::of($resumen['saldo'])->isZero())throw ValidationException::withMessages(['estado'=>'La orden debe estar totalmente pagada antes de la entrega.']);}$anterior=$bloqueada->estado;$c=['estado'=>$nuevo,'actualizado_por'=>$request->user()->id];if($nuevo==='finalizada')$c['finalizada_en']=now();if($nuevo==='entregada')$c['entregada_en']=now();if($nuevo==='cancelada')$c=[...$c,'motivo_cancelacion'=>$request->validated('motivo'),'cancelada_en'=>now(),'cancelada_por'=>$request->user()->id];$bloqueada->update($c);OrdenEstadoHistorialEloquentModel::create(['orden_id'=>$bloqueada->id,'estado_anterior'=>$anterior,'estado_nuevo'=>$nuevo,'observaciones'=>$request->validated('observaciones')?:$request->validated('motivo'),'usuario_id'=>$request->user()->id]);});
        */
    }

    public function diagnosticar(RegistrarDiagnosticoRequest $request,OrdenTrabajoEloquentModel $orden,RegistrarAuditoria $auditoria,RegistrarEventoVehiculo $historial): RedirectResponse
    {
        return $this->guardarDiagnostico($request, $orden, $auditoria, $historial);
    }

    public function cambiarEstadoServicio(CambiarEstadoServicioOrdenRequest$request,OrdenTrabajoEloquentModel$orden,OrdenServicioEloquentModel$servicio,ValidarPreparacionTrabajo$preparacion,RegistrarAuditoria$auditoria,RegistrarEventoVehiculo$historial):RedirectResponse
    {
        return $this->procesarEstadoServicio($request, $orden, $servicio, $preparacion, $auditoria, $historial);
    }

    public function agregarServicio(GuardarServicioOrdenRequest $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria): RedirectResponse
    {
        return $this->guardarServicioAdicional($request, $orden, $auditoria);
    }

    public function agregarRepuestoRequerido(GuardarRepuestoRequeridoRequest $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria): RedirectResponse
    {
        return $this->guardarRequerimiento($request, $orden, $auditoria);
    }

    public function registrarAvance(RegistrarAvanceRequest $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria, RegistrarEventoVehiculo $historial): RedirectResponse
    {
        return $this->guardarAvance($request, $orden, $auditoria, $historial);
    }

    public function actualizarCierreTecnico(ActualizarCierreTecnicoRequest $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria): RedirectResponse
    {
        return $this->guardarCierreTecnico($request, $orden, $auditoria);
    }

    public function aprobarServicio(AprobarServicioOrdenRequest $request, OrdenTrabajoEloquentModel $orden, OrdenServicioEloquentModel $servicio, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $this->autorizarVista($request, $orden);
        DB::transaction(function () use ($request, $orden, $servicio) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            abort_unless($request->user()->can('mutate', $bloqueada), 403);
            $linea = OrdenServicioEloquentModel::whereKey($servicio->id)->where('orden_id', $bloqueada->id)->lockForUpdate()->firstOrFail();
            if ($linea->aprobacion_estado !== 'pendiente_aprobacion') throw ValidationException::withMessages(['servicio' => 'El servicio ya fue decidido.']);
            $nuevo = $request->validated('estado');
            $linea->update(['aprobacion_estado' => $nuevo, 'aprobado_en' => $nuevo === 'aprobado' ? now() : null, 'aprobado_por' => $request->user()->id, 'estado' => $nuevo === 'rechazado' ? 'cancelado' : 'pendiente', 'observaciones' => $request->validated('motivo')]);
            $this->registrarHistorialServicio($linea->id, 'pendiente_aprobacion', $nuevo, $request->validated('motivo'), $request->user()->id);
        });
        $auditoria->registrar('orden.servicio_aprobacion', 'orden_servicio', $servicio->id, ['estado' => $request->validated('estado')], $request);
        return back()->with('success', 'Decisión del servicio registrada.');
    }

    public function actualizarRepuestoRequerido(ActualizarRepuestoRequeridoRequest $request, OrdenTrabajoEloquentModel $orden, OrdenRepuestoRequeridoEloquentModel $requerimiento, RegistrarAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $orden, $requerimiento) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $this->autorizarMecanicoAsignado($request, $bloqueada);
            $item = OrdenRepuestoRequeridoEloquentModel::whereKey($requerimiento->id)->where('orden_id', $bloqueada->id)->lockForUpdate()->firstOrFail();
            if (OrdenRepuestoEloquentModel::where('requerimiento_id', $item->id)->whereNull('revertido_en')->exists()) throw ValidationException::withMessages(['cantidad' => 'No puedes editar un requerimiento que ya tiene consumos.']);
            if (! in_array($item->estado, ['pendiente_aprobacion', 'aprobado', 'no_disponible'], true)) throw ValidationException::withMessages(['repuesto' => 'El requerimiento ya no es editable.']);
            $anterior = $item->estado;
            $item->update(['cantidad' => $request->validated('cantidad'), 'prioridad' => $request->validated('prioridad'), 'obligatorio' => $request->validated('obligatorio'), 'motivo' => $request->validated('motivo'), 'estado' => 'pendiente_aprobacion', 'aprobado_en' => null, 'aprobado_por' => null, 'actualizado_por' => $request->user()->id]);
            $this->registrarHistorialRequerimiento($item, $anterior, 'pendiente_aprobacion', 'Requerimiento editado: '.$request->validated('motivo'), $request->user()->id);
        });
        $auditoria->registrar('orden.repuesto_requerido_actualizado', 'orden_repuesto_requerido', $requerimiento->id, [], $request);
        return back()->with('success', 'Requerimiento actualizado y enviado nuevamente a aprobación.');
    }

    public function cambiarEstadoRepuestoRequerido(CambiarEstadoRepuestoRequeridoRequest $request, OrdenTrabajoEloquentModel $orden, OrdenRepuestoRequeridoEloquentModel $requerimiento, RegistrarAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $orden, $requerimiento) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $nuevo = $request->validated('estado');
            if (! in_array($nuevo, ['aprobado', 'no_disponible'], true)) $this->autorizarMecanicoAsignado($request, $bloqueada);
            else abort_unless($request->user()->can('mutate', $bloqueada), 403);
            $item = OrdenRepuestoRequeridoEloquentModel::whereKey($requerimiento->id)->where('orden_id', $bloqueada->id)->lockForUpdate()->firstOrFail();
            if (OrdenRepuestoEloquentModel::where('requerimiento_id', $item->id)->whereNull('revertido_en')->exists() && in_array($nuevo, ['cancelado', 'no_utilizado'], true)) throw ValidationException::withMessages(['repuesto' => 'Primero revierte los repuestos utilizados.']);
            if ($nuevo === 'aprobado' && $item->fuente_suministro === 'externo' && $request->validated('precioUnitarioAprobado') === null) throw ValidationException::withMessages(['precioUnitarioAprobado' => 'Indica el precio aprobado del repuesto externo.']);
            $anterior = $item->estado;
            $precio = $item->fuente_suministro === 'cliente' ? '0.00' : ($request->validated('precioUnitarioAprobado') ?? $item->precio_unitario_aprobado);
            $item->update(['estado' => $nuevo, 'precio_unitario_aprobado' => $precio, 'aprobado_en' => $nuevo === 'aprobado' ? now() : null, 'aprobado_por' => in_array($nuevo, ['aprobado', 'no_disponible'], true) ? $request->user()->id : $item->aprobado_por, 'retirado_en' => $nuevo === 'cancelado' ? now() : null, 'retirado_por' => $nuevo === 'cancelado' ? $request->user()->id : null, 'motivo_retiro' => $nuevo === 'cancelado' ? $request->validated('motivo') : null, 'actualizado_por' => $request->user()->id]);
            $this->registrarHistorialRequerimiento($item, $anterior, $nuevo, $request->validated('motivo'), $request->user()->id);
        });
        $auditoria->registrar('orden.repuesto_requerido_estado', 'orden_repuesto_requerido', $requerimiento->id, ['estado' => $request->validated('estado')], $request);
        return back()->with('success', 'Estado del requerimiento actualizado sin afectar inventario.');
    }

    private function procesarCambioEstado(CambiarEstadoOrdenRequest $request, OrdenTrabajoEloquentModel $orden, CalculadorTotalOrden $calculador, ValidarPreparacionTrabajo $preparacion, FlujoEstadosOrden $flujo, CerrarRecursosOrdenCancelada $cerrarCancelada, RegistrarAuditoria $auditoria, RegistrarEventoVehiculo $historial): RedirectResponse
    {
        $nuevo = $request->validated('estado');
        $this->autorizarTransicion($request, $orden, $nuevo);
        DB::transaction(function () use ($request, $orden, $nuevo, $calculador, $preparacion, $flujo, $cerrarCancelada) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $this->autorizarTransicion($request, $bloqueada, $nuevo);
            if (! $flujo->permite($bloqueada->estado, $nuevo, $bloqueada->estado_anterior_pausa)) throw ValidationException::withMessages(['estado' => "Transición inválida de {$bloqueada->estado} a {$nuevo}."]);
            if ($nuevo === 'asignada' && ! $bloqueada->asignaciones()->where('activo', true)->exists()) throw ValidationException::withMessages(['estado' => 'Asigna al menos un mecánico antes de continuar.']);
            if ($nuevo === 'en_diagnostico' && $bloqueada->cita_id && ! CitaEloquentModel::whereKey($bloqueada->cita_id)->where('estado', 'atendida')->exists()) throw ValidationException::withMessages(['estado' => 'Primero registra la llegada del vehículo.']);
            if ($nuevo === 'en_reparacion') $preparacion->validar($bloqueada->id);
            if ($nuevo === 'finalizada') {
                $this->validarCierreTecnico($bloqueada);
                if (! DiagnosticoTecnicoEloquentModel::where('orden_id', $bloqueada->id)->publicadoActual()->exists()) throw ValidationException::withMessages(['estado' => 'Confirma el diagnóstico técnico antes de finalizar.']);
                if (OrdenServicioEloquentModel::where('orden_id', $bloqueada->id)->where(fn ($q) => $q->whereIn('estado', ['pendiente', 'en_proceso'])->orWhere('aprobacion_estado', 'pendiente_aprobacion'))->exists()) throw ValidationException::withMessages(['estado' => 'Resuelve todos los servicios y aprobaciones pendientes.']);
                if (OrdenRepuestoRequeridoEloquentModel::where('orden_id', $bloqueada->id)->where('obligatorio', true)->whereNotIn('estado', ['utilizado', 'no_utilizado', 'cancelado'])->exists()) throw ValidationException::withMessages(['estado' => 'Resuelve todos los repuestos obligatorios antes de finalizar.']);
                $hayTrabajo = OrdenServicioEloquentModel::where('orden_id', $bloqueada->id)->where('estado', 'completado')->exists() || OrdenRepuestoEloquentModel::where('orden_id', $bloqueada->id)->whereNull('revertido_en')->exists();
                if (! $hayTrabajo) throw ValidationException::withMessages(['estado' => 'Registra al menos un servicio completado o repuesto utilizado.']);
            }
            if ($nuevo === 'cancelada') {
                if (PagoEloquentModel::where('orden_id', $bloqueada->id)->where('estado', 'registrado')->exists()) throw ValidationException::withMessages(['estado' => 'Primero anula los pagos registrados.']);
                if (OrdenRepuestoEloquentModel::where('orden_id', $bloqueada->id)->whereNull('revertido_en')->exists()) throw ValidationException::withMessages(['estado' => 'Primero revierte los repuestos utilizados.']);
            }
            if ($nuevo === 'lista_entrega' && ! FacturaOrdenEloquentModel::where('orden_id', $bloqueada->id)->where('estado', 'emitida')->exists()) throw ValidationException::withMessages(['estado' => 'Emite la factura definitiva antes de preparar la entrega.']);
            if ($nuevo === 'entregada' && ! BigDecimal::of($calculador->calcular($bloqueada->id)['saldo'])->isZero()) throw ValidationException::withMessages(['estado' => 'La orden debe estar totalmente pagada antes de la entrega.']);
            $anterior = $bloqueada->estado;
            $cambios = ['estado' => $nuevo, 'actualizado_por' => $request->user()->id];
            if ($nuevo === 'pausada') $cambios['estado_anterior_pausa'] = $anterior;
            elseif ($anterior === 'pausada') $cambios['estado_anterior_pausa'] = null;
            if ($nuevo === 'finalizada') $cambios['finalizada_en'] = now();
            if ($nuevo === 'lista_entrega') $cambios['observaciones_entrega'] = $request->validated('observacionesEntrega');
            if ($nuevo === 'entregada') $cambios['entregada_en'] = now();
            if ($nuevo === 'cancelada') $cambios = [...$cambios, 'motivo_cancelacion' => $request->validated('motivo'), 'cancelada_en' => now(), 'cancelada_por' => $request->user()->id];
            $bloqueada->update($cambios);
            if ($nuevo === 'cancelada') $cerrarCancelada->ejecutar($bloqueada, $request->user()->id);
            elseif ($nuevo === 'entregada') $bloqueada->asignaciones()->where('activo', true)->update(['activo' => false, 'retirado_en' => now()]);
            if ($nuevo === 'finalizada') $this->cerrarSugerenciaIa($bloqueada->id, $request->user()->id);
            OrdenEstadoHistorialEloquentModel::create(['orden_id' => $bloqueada->id, 'estado_anterior' => $anterior, 'estado_nuevo' => $nuevo, 'observaciones' => $request->validated('motivo') ?: $request->validated('observaciones') ?: $request->validated('observacionesEntrega'), 'usuario_id' => $request->user()->id]);
        });
        $auditoria->registrar('orden.estado_cambiado', 'orden_trabajo', $orden->id, ['estado' => $nuevo], $request);
        $historial->registrar($orden->vehiculo_id, 'orden.estado_cambiado', "La orden {$orden->numero} cambió a {$nuevo}.", ['orden_id' => $orden->id, 'estado' => $nuevo], $request);
        return back()->with('success', 'Estado de la orden actualizado.');
    }

    private function guardarDiagnostico(RegistrarDiagnosticoRequest $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria, RegistrarEventoVehiculo $historial): RedirectResponse
    {
        $datos = $request->validated();
        $version = DB::transaction(function () use ($request, $orden, $datos) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $cerrada = in_array($bloqueada->estado, ['finalizada', 'lista_entrega', 'entregada', 'cancelada'], true);
            abort_unless($request->user()->can($cerrada ? 'correctDiagnosis' : 'technicalWork', $bloqueada), 403);
            if (! $cerrada && ! in_array($bloqueada->estado, ['en_diagnostico', 'esperando_aprobacion', 'esperando_repuestos', 'en_reparacion', 'pausada', 'en_prueba'], true)) throw ValidationException::withMessages(['diagnostico' => 'La orden aún no admite diagnóstico técnico.']);
            $actuales = DiagnosticoTecnicoEloquentModel::where('orden_id', $bloqueada->id)->where('vigente', true)->lockForUpdate()->get()->keyBy('estado');
            $borradorActual = $actuales->get('borrador');
            $publicadoActual = $actuales->get('confirmado');
            $numero = ((int) DiagnosticoTecnicoEloquentModel::where('orden_id', $bloqueada->id)->max('version')) + 1;
            if ($datos['estado'] === 'borrador') {
                $reemplaza = $borradorActual ?? $publicadoActual;
                $borradorActual?->update(['vigente' => false]);
            } else {
                $reemplaza = $borradorActual ?? $publicadoActual;
                DiagnosticoTecnicoEloquentModel::where('orden_id', $bloqueada->id)->where('vigente', true)->whereIn('estado', ['borrador', 'confirmado'])->update(['vigente' => false]);
            }
            $mecanico = MecanicoEloquentModel::where('usuario_id', $request->user()->id)->value('id');
            DiagnosticoTecnicoEloquentModel::create(['orden_id' => $bloqueada->id, 'mecanico_id' => $mecanico, 'version' => $numero, 'diagnostico' => $datos['diagnostico'], 'causa' => $datos['causa'] ?? null, 'componentes_afectados' => $datos['componentesAfectados'] ?? null, 'severidad' => $datos['severidad'], 'resumen_cliente' => $datos['resumenCliente'] ?? null, 'pruebas_realizadas' => $datos['pruebasRealizadas'] ?? null, 'recomendaciones' => $datos['recomendaciones'] ?? null, 'observaciones_tecnicas' => $datos['observacionesTecnicas'] ?? null, 'indicaciones_seguridad' => $datos['indicacionesSeguridad'] ?? null, 'puede_circular' => $datos['puedeCircular'], 'proximo_mantenimiento_en' => $datos['proximoMantenimientoEn'] ?? null, 'notas_internas' => $datos['notasInternas'] ?? null, 'estado' => $datos['estado'], 'motivo_correccion' => $datos['motivoCorreccion'] ?? null, 'confirmado_en' => $datos['estado'] === 'confirmado' ? now() : null, 'reemplaza_id' => $reemplaza?->id, 'vigente' => true, 'registrado_por' => $request->user()->id]);
            if ($datos['estado'] === 'confirmado' && ! empty($datos['proximoMantenimientoEn'])) $bloqueada->update(['proximo_mantenimiento_en' => $datos['proximoMantenimientoEn'], 'actualizado_por' => $request->user()->id]);
            return $numero;
        });
        $auditoria->registrar('diagnostico.version_registrada', 'orden_trabajo', $orden->id, ['version' => $version, 'estado' => $datos['estado']], $request);
        $historial->registrar($orden->vehiculo_id, 'diagnostico.registrado', "Se registró la versión {$version} del diagnóstico en {$orden->numero}.", ['orden_id' => $orden->id, 'version' => $version, 'estado' => $datos['estado']], $request);
        return back()->with('success', $datos['estado'] === 'borrador' ? 'Borrador guardado con historial.' : 'Diagnóstico confirmado y publicado para el cliente.');
    }

    private function procesarEstadoServicio(CambiarEstadoServicioOrdenRequest $request, OrdenTrabajoEloquentModel $orden, OrdenServicioEloquentModel $servicio, ValidarPreparacionTrabajo $preparacion, RegistrarAuditoria $auditoria, RegistrarEventoVehiculo $historial): RedirectResponse
    {
        $datos = $request->validated();
        DB::transaction(function () use ($request, $orden, $servicio, $datos, $preparacion) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $this->autorizarMecanicoAsignado($request, $bloqueada);
            if (! in_array($bloqueada->estado, ['en_diagnostico', 'en_reparacion', 'en_prueba'], true)) throw ValidationException::withMessages(['servicio' => 'La orden no admite cambios de trabajo en su estado actual.']);
            $linea = OrdenServicioEloquentModel::whereKey($servicio->id)->where('orden_id', $bloqueada->id)->lockForUpdate()->firstOrFail();
            if ($linea->aprobacion_estado !== 'aprobado') throw ValidationException::withMessages(['servicio' => 'El servicio adicional debe estar aprobado.']);
            if ($datos['estado'] === 'completado') $preparacion->validar($bloqueada->id);
            $mapa = ['pendiente' => ['en_proceso', 'completado', 'cancelado'], 'en_proceso' => ['completado', 'cancelado'], 'completado' => [], 'cancelado' => []];
            if (! in_array($datos['estado'], $mapa[$linea->estado] ?? [], true)) throw ValidationException::withMessages(['servicio' => 'Transición inválida del servicio.']);
            $anterior = $linea->estado;
            $linea->update(['estado' => $datos['estado'], 'observaciones' => $datos['observaciones'] ?? $linea->observaciones, 'trabajo_realizado' => $datos['trabajoRealizado'] ?? $linea->trabajo_realizado, 'iniciado_en' => $datos['estado'] === 'en_proceso' ? now() : $linea->iniciado_en, 'iniciado_por' => $datos['estado'] === 'en_proceso' ? $request->user()->id : $linea->iniciado_por, 'tiempo_trabajado_minutos' => $datos['tiempoTrabajadoMinutos'] ?? $linea->tiempo_trabajado_minutos, 'resultado_prueba' => $datos['resultadoPrueba'] ?? null, 'observaciones_posteriores' => $datos['observacionesPosteriores'] ?? null, 'recomendaciones_cliente' => $datos['recomendacionesCliente'] ?? null, 'completado_en' => $datos['estado'] === 'completado' ? now() : null, 'completado_por' => $datos['estado'] === 'completado' ? $request->user()->id : null]);
            $this->registrarHistorialServicio($linea->id, $anterior, $datos['estado'], $datos['trabajoRealizado'] ?? $datos['observaciones'] ?? null, $request->user()->id);
        });
        $auditoria->registrar('orden.servicio_estado_cambiado', 'orden_servicio', $servicio->id, ['estado' => $datos['estado']], $request);
        if ($datos['estado'] === 'completado') $historial->registrar($orden->vehiculo_id, 'servicio.finalizado', "Se completó {$servicio->nombre_servicio} en {$orden->numero}.", ['orden_id' => $orden->id, 'servicio_id' => $servicio->id], $request);
        return back()->with('success', 'Trabajo del servicio actualizado.');
    }

    private function guardarServicioAdicional(GuardarServicioOrdenRequest $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $linea = DB::transaction(function () use ($request, $orden) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $this->autorizarMecanicoAsignado($request, $bloqueada);
            if (! in_array($bloqueada->estado, ['en_diagnostico', 'esperando_aprobacion', 'en_reparacion'], true)) throw ValidationException::withMessages(['servicioId' => 'La orden no admite trabajos adicionales en su estado actual.']);
            $catalogo = ServicioEloquentModel::whereKey($request->validated('servicioId'))->where('estado', 'activo')->firstOrFail();
            $linea = OrdenServicioEloquentModel::where('orden_id', $bloqueada->id)->where('servicio_id', $catalogo->id)->lockForUpdate()->first();
            if ($linea && $linea->estado !== 'cancelado') throw ValidationException::withMessages(['servicioId' => 'Este servicio ya está incluido.']);
            if ($linea) {
                $linea->update(['nombre_servicio' => $catalogo->nombre, 'precio_acordado' => $catalogo->precio_base, 'estado' => 'pendiente', 'observaciones' => $request->validated('motivo'), 'origen' => 'mecanico', 'tipo_trabajo' => 'adicional', 'aprobacion_estado' => 'pendiente_aprobacion', 'aprobado_en' => null, 'aprobado_por' => null, 'trabajo_realizado' => null, 'iniciado_en' => null, 'iniciado_por' => null, 'tiempo_trabajado_minutos' => 0, 'resultado_prueba' => null, 'observaciones_posteriores' => null, 'recomendaciones_cliente' => null, 'completado_en' => null, 'completado_por' => null, 'agregado_por' => $request->user()->id]);
                $this->registrarHistorialServicio($linea->id, 'cancelado', 'pendiente_aprobacion', $request->validated('motivo'), $request->user()->id);
            } else {
                $linea = OrdenServicioEloquentModel::create(['orden_id' => $bloqueada->id, 'servicio_id' => $catalogo->id, 'nombre_servicio' => $catalogo->nombre, 'precio_acordado' => $catalogo->precio_base, 'estado' => 'pendiente', 'observaciones' => $request->validated('motivo'), 'origen' => 'mecanico', 'tipo_trabajo' => 'adicional', 'aprobacion_estado' => 'pendiente_aprobacion', 'agregado_por' => $request->user()->id]);
                $this->registrarHistorialServicio($linea->id, null, 'pendiente_aprobacion', $request->validated('motivo'), $request->user()->id);
            }
            return $linea;
        });
        $auditoria->registrar('orden.servicio_adicional_solicitado', 'orden_servicio', $linea->id, [], $request);
        return back()->with('success', 'Trabajo adicional registrado y pendiente de aprobación.');
    }

    private function guardarRequerimiento(GuardarRepuestoRequeridoRequest $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $item = DB::transaction(function () use ($request, $orden) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $this->autorizarMecanicoAsignado($request, $bloqueada);
            if (! in_array($bloqueada->estado, ['en_diagnostico', 'esperando_aprobacion', 'esperando_repuestos', 'en_reparacion'], true)) throw ValidationException::withMessages(['repuestoId' => 'La orden no admite nuevos repuestos en su estado actual.']);
            $fuente = $request->validated('fuenteSuministro');
            $repuesto = $request->validated('repuestoId') ? RepuestoEloquentModel::findOrFail($request->validated('repuestoId')) : null;
            if ($fuente === 'inventario' && ! $repuesto) throw ValidationException::withMessages(['repuestoId' => 'Selecciona un repuesto del inventario.']);
            $item = OrdenRepuestoRequeridoEloquentModel::create(['orden_id' => $bloqueada->id, 'repuesto_id' => $repuesto?->id, 'origen' => 'mecanico', 'descripcion' => $request->validated('descripcion'), 'cantidad' => $request->validated('cantidad'), 'motivo' => $request->validated('motivo'), 'estado' => 'pendiente_aprobacion', 'prioridad' => $request->validated('prioridad'), 'obligatorio' => $request->validated('obligatorio'), 'fuente_suministro' => $fuente, 'unidad_snapshot' => $repuesto?->unidad ?: $request->validated('unidad'), 'agregado_por' => $request->user()->id, 'actualizado_por' => $request->user()->id]);
            $this->registrarHistorialRequerimiento($item, null, 'pendiente_aprobacion', $request->validated('motivo'), $request->user()->id);
            return $item;
        });
        $auditoria->registrar('orden.repuesto_requerido', 'orden_repuesto_requerido', $item->id, [], $request);
        return back()->with('success', 'Repuesto requerido registrado sin descontar inventario.');
    }

    private function guardarAvance(RegistrarAvanceRequest $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria, RegistrarEventoVehiculo $historial): RedirectResponse
    {
        $avance = DB::transaction(function () use ($request, $orden) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $this->autorizarMecanicoAsignado($request, $bloqueada);
            if (! in_array($bloqueada->estado, ['en_diagnostico', 'esperando_aprobacion', 'esperando_repuestos', 'en_reparacion', 'pausada', 'en_prueba'], true)) throw ValidationException::withMessages(['avance' => 'La orden no admite avances en su estado actual.']);
            if ($request->validated('servicioId') && ! OrdenServicioEloquentModel::whereKey($request->validated('servicioId'))->where('orden_id', $bloqueada->id)->exists()) throw ValidationException::withMessages(['servicioId' => 'El servicio no pertenece a la orden.']);
            $avance = OrdenAvanceEloquentModel::create(['orden_id' => $bloqueada->id, 'servicio_id' => $request->validated('servicioId'), 'tipo' => $request->validated('tipo'), 'descripcion' => $request->validated('descripcion'), 'visibilidad' => $request->validated('visibilidad'), 'estado_orden' => $bloqueada->estado, 'porcentaje' => $request->validated('porcentaje'), 'fecha_estimada_finalizacion' => $request->validated('fechaEstimadaFinalizacion'), 'nota_interna' => $request->validated('notaInterna'), 'registrado_por' => $request->user()->id]);
            if ($request->validated('fechaEstimadaFinalizacion')) $bloqueada->update(['fecha_estimada_finalizacion' => $request->validated('fechaEstimadaFinalizacion'), 'actualizado_por' => $request->user()->id]);
            return $avance;
        });
        $auditoria->registrar('orden.avance_registrado', 'orden_avance', $avance->id, ['tipo' => $avance->tipo, 'visibilidad' => $avance->visibilidad], $request);
        $historial->registrar($orden->vehiculo_id, 'orden.avance_registrado', "Se registró una actualización en {$orden->numero}.", ['orden_id' => $orden->id, 'avance_id' => $avance->id, 'visibilidad' => $avance->visibilidad], $request);
        return back()->with('success', 'Actualización registrada en la línea de tiempo.');
    }

    private function guardarCierreTecnico(ActualizarCierreTecnicoRequest $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $datos = $request->validated();
        DB::transaction(function () use ($request, $orden, $datos) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $this->autorizarMecanicoAsignado($request, $bloqueada);
            if (! in_array($bloqueada->estado, ['en_diagnostico', 'esperando_aprobacion', 'esperando_repuestos', 'en_reparacion', 'pausada', 'en_prueba'], true)) throw ValidationException::withMessages(['cierreTecnico' => 'La orden ya no admite cambios técnicos.']);
            $bloqueada->update(['tiempo_trabajado_minutos' => $datos['tiempoTrabajadoMinutos'], 'bloqueos_tecnicos' => $datos['bloqueosTecnicos'] ?? null, 'control_calidad_estado' => $datos['controlCalidadEstado'], 'control_calidad_notas' => $datos['controlCalidadNotas'] ?? null, 'prueba_ruta_estado' => $datos['pruebaRutaEstado'], 'prueba_ruta_notas' => $datos['pruebaRutaNotas'] ?? null, 'observaciones_entrega' => $datos['observacionesEntrega'] ?? null, 'proximo_mantenimiento_en' => $datos['proximoMantenimientoEn'] ?? null, 'cierre_tecnico_actualizado_en' => now(), 'cierre_tecnico_actualizado_por' => $request->user()->id, 'actualizado_por' => $request->user()->id]);
            DB::table('orden_cierre_tecnico_historial')->insert(['id' => (string) Str::uuid(), 'orden_id' => $bloqueada->id, 'tiempo_trabajado_minutos' => $datos['tiempoTrabajadoMinutos'], 'bloqueos_tecnicos' => $datos['bloqueosTecnicos'] ?? null, 'control_calidad_estado' => $datos['controlCalidadEstado'], 'control_calidad_notas' => $datos['controlCalidadNotas'] ?? null, 'prueba_ruta_estado' => $datos['pruebaRutaEstado'], 'prueba_ruta_notas' => $datos['pruebaRutaNotas'] ?? null, 'registrado_por' => $request->user()->id, 'created_at' => now()]);
        });
        $auditoria->registrar('orden.cierre_tecnico_actualizado', 'orden_trabajo', $orden->id, ['despues' => $datos], $request);
        return back()->with('success', 'Expediente técnico actualizado con historial.');
    }

    private function registrarHistorialRequerimiento(OrdenRepuestoRequeridoEloquentModel $item, ?string $anterior, string $nuevo, ?string $motivo, ?string $usuario): void
    {
        DB::table('orden_repuesto_requerido_historial')->insert(['id' => (string) Str::uuid(), 'requerimiento_id' => $item->id, 'estado_anterior' => $anterior, 'estado_nuevo' => $nuevo, 'cantidad' => $item->cantidad, 'motivo' => $motivo, 'usuario_id' => $usuario, 'created_at' => now()]);
    }

    private function registrarHistorialServicio(string $servicioId, ?string $anterior, string $nuevo, ?string $detalle, ?string $usuario): void
    {
        DB::table('orden_servicio_historial')->insert(['id' => (string) Str::uuid(), 'orden_servicio_id' => $servicioId, 'estado_anterior' => $anterior, 'estado_nuevo' => $nuevo, 'detalle' => $detalle, 'usuario_id' => $usuario, 'created_at' => now()]);
    }

    private function validarCierreTecnico(OrdenTrabajoEloquentModel $orden): void
    {
        if ($orden->tiempo_trabajado_minutos < 1) throw ValidationException::withMessages(['estado' => 'Registra el tiempo trabajado antes de finalizar la orden.']);
        if (filled($orden->bloqueos_tecnicos)) throw ValidationException::withMessages(['estado' => 'Resuelve los bloqueos técnicos antes de finalizar la orden.']);
        if ($orden->control_calidad_estado !== 'aprobado') throw ValidationException::withMessages(['estado' => 'El control de calidad debe estar aprobado antes de finalizar.']);
        if (! in_array($orden->prueba_ruta_estado, ['aprobada', 'no_aplica'], true)) throw ValidationException::withMessages(['estado' => 'La prueba de ruta debe estar aprobada o marcada como no aplicable.']);
    }

    private function cerrarSugerenciaIa(string $ordenId, string $usuarioId): void
    {
        $consulta = ConsultaIaEloquentModel::where('orden_id', $ordenId)->lockForUpdate()->first();
        if (! $consulta || in_array($consulta->estado, ['descartada', 'cerrada'], true)) return;

        $version = (int) RevisionSugerenciaIaEloquentModel::where('consulta_id', $consulta->id)->max('version') + 1;
        RevisionSugerenciaIaEloquentModel::create([
            'consulta_id' => $consulta->id,
            'version' => $version,
            'estado_anterior' => $consulta->estado,
            'estado_nuevo' => 'cerrada',
            'observaciones' => 'Sugerencia cerrada automáticamente al finalizar técnicamente la orden.',
            'mecanico_id' => MecanicoEloquentModel::where('usuario_id', $usuarioId)->value('id'),
            'revisada_por' => $usuarioId,
        ]);
        $consulta->update(['estado' => 'cerrada']);
    }

    private function crearOrden(string$cliente,string$vehiculo,string$falla,?int$km,array$servicios,array$mecanicos,string$usuario): OrdenTrabajoEloquentModel
    {
        $orden=OrdenTrabajoEloquentModel::create(['numero'=>'OT-'.now()->format('Ymd').'-'.mb_strtoupper(substr(str_replace('-','',(string)str()->uuid()),0,6)),'cliente_id'=>$cliente,'vehiculo_id'=>$vehiculo,'falla_reportada'=>$falla,'kilometraje'=>$km,'estado'=>'pendiente','creado_por'=>$usuario,'actualizado_por'=>$usuario]);
        foreach(ServicioEloquentModel::whereIn('id',$servicios)->get()as$s)OrdenServicioEloquentModel::create(['orden_id'=>$orden->id,'servicio_id'=>$s->id,'nombre_servicio'=>$s->nombre,'precio_acordado'=>$s->precio_base,'estado'=>'pendiente']);foreach($mecanicos as$id)OrdenMecanicoEloquentModel::create(['orden_id'=>$orden->id,'mecanico_id'=>$id,'activo'=>true,'asignado_por'=>$usuario]);OrdenEstadoHistorialEloquentModel::create(['orden_id'=>$orden->id,'estado_nuevo'=>'pendiente','observaciones'=>'Orden creada','usuario_id'=>$usuario]);return $orden;
    }
    private function catalogos():array{return['clientes'=>ClienteEloquentModel::with(['vehiculos'=>fn($q)=>$q->where('estado','activo')])->where('estado','activo')->orderBy('razon_social')->get()->map(fn($c)=>['id'=>$c->id,'nombre'=>$c->razon_social,'vehiculos'=>$c->vehiculos->map(fn($v)=>['id'=>$v->id,'label'=>"{$v->placa} · {$v->marca} {$v->modelo}",'kilometraje'=>$v->kilometraje])]),'servicios'=>ServicioEloquentModel::where('estado','activo')->orderBy('nombre')->get()->map(fn($s)=>['label'=>"{$s->nombre} · $ {$s->precio_base}",'value'=>$s->id]),'mecanicos'=>MecanicoEloquentModel::where('estado','activo')->orderBy('apellidos')->get()->map(fn($m)=>['label'=>"{$m->nombres} {$m->apellidos}",'value'=>$m->id])];}
    private function autorizarVista(Request$r,OrdenTrabajoEloquentModel$o):void{abort_unless($r->user()->can('view',$o),403);}
    private function autorizarMecanicoAsignado(Request$r,OrdenTrabajoEloquentModel$o):void{app(AutorizarMecanicoOrden::class)->autorizar($r->user(),$o);}
    private function autorizarTransicion(Request$r,OrdenTrabajoEloquentModel$o,string$nuevo):void{$ability=match($nuevo){'asignada'=>'assign','cancelada'=>'cancel','lista_entrega','entregada'=>'deliver',default=>'technicalWork'};abort_unless($r->user()->can($ability,$o),403);}
    private function resumen($o):array{return['id'=>$o->id,'numero'=>$o->numero,'cliente'=>$o->cliente?->razon_social,'vehiculo'=>$o->vehiculo?->placa,'estado'=>$o->estado,'recibidaEn'=>$o->recibida_en->toIso8601String(),'mecanicos'=>$o->asignaciones->map(fn($a)=>$a->mecanico?->nombres.' '.$a->mecanico?->apellidos)->filter()->values()];}
    private function detalle($o, bool $esCliente = false, bool $puedeVerFinanzas = false): array
    {
        $diagnosticos = $esCliente ? $o->diagnosticos->where('vigente', true)->where('estado', 'confirmado')->take(1) : $o->diagnosticos;
        $servicios = $esCliente ? $o->servicios->where('aprobacion_estado', 'aprobado')->where('estado', '<>', 'cancelado') : $o->servicios;
        return [
            ...$this->resumen($o),
            'fallaReportada' => $o->falla_reportada,
            'kilometraje' => $o->kilometraje,
            'estadoAnteriorPausa' => $o->estado_anterior_pausa,
            'ultimaActualizacion' => $o->updated_at?->toIso8601String(),
            'fechaEstimadaFinalizacion' => $o->fecha_estimada_finalizacion?->toIso8601String(),
            'observacionesEntrega' => $o->observaciones_entrega,
            'proximoMantenimientoEn' => $o->proximo_mantenimiento_en?->toDateString(),
            'servicios' => $servicios->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre_servicio, 'precio' => $puedeVerFinanzas ? $s->precio_acordado : null, 'estado' => $s->estado, 'aprobacionEstado' => $s->aprobacion_estado, 'tipoTrabajo' => $s->tipo_trabajo, 'observaciones' => $esCliente ? null : $s->observaciones, 'origen' => $esCliente ? null : $s->origen, 'trabajoRealizado' => $s->trabajo_realizado, 'tiempoTrabajadoMinutos' => $esCliente ? null : $s->tiempo_trabajado_minutos, 'resultadoPrueba' => $s->resultado_prueba, 'observacionesPosteriores' => $s->observaciones_posteriores, 'recomendacionesCliente' => $s->recomendaciones_cliente, 'iniciadoEn' => $s->iniciado_en?->toIso8601String(), 'completadoEn' => $s->completado_en?->toIso8601String()])->values(),
            'mecanicoIds' => $esCliente ? [] : $o->asignaciones->pluck('mecanico_id'),
            'cierreTecnico' => $esCliente ? null : ['tiempoTrabajadoMinutos' => $o->tiempo_trabajado_minutos, 'bloqueosTecnicos' => $o->bloqueos_tecnicos, 'controlCalidadEstado' => $o->control_calidad_estado, 'controlCalidadNotas' => $o->control_calidad_notas, 'pruebaRutaEstado' => $o->prueba_ruta_estado, 'pruebaRutaNotas' => $o->prueba_ruta_notas, 'observacionesEntrega' => $o->observaciones_entrega, 'proximoMantenimientoEn' => $o->proximo_mantenimiento_en?->toDateString(), 'actualizadoEn' => $o->cierre_tecnico_actualizado_en?->toIso8601String()],
            'diagnosticos' => $diagnosticos->map(fn ($d) => ['id' => $d->id, 'version' => $d->version, 'estado' => $d->estado, 'diagnostico' => $esCliente ? $d->resumen_cliente : $d->diagnostico, 'causa' => $esCliente ? null : $d->causa, 'componentesAfectados' => $esCliente ? null : $d->componentes_afectados, 'severidad' => $d->severidad, 'resumenCliente' => $d->resumen_cliente, 'pruebasRealizadas' => $esCliente ? null : $d->pruebas_realizadas, 'recomendaciones' => $d->recomendaciones, 'observacionesTecnicas' => $esCliente ? null : $d->observaciones_tecnicas, 'indicacionesSeguridad' => $d->indicaciones_seguridad, 'puedeCircular' => $d->puede_circular, 'proximoMantenimientoEn' => $d->proximo_mantenimiento_en?->toDateString(), 'notasInternas' => $esCliente ? null : $d->notas_internas, 'motivoCorreccion' => $esCliente ? null : $d->motivo_correccion, 'autor' => $esCliente ? 'Taller' : $d->autor?->name, 'confirmadoEn' => $d->confirmado_en?->toIso8601String(), 'vigente' => $d->vigente, 'createdAt' => $d->created_at?->toIso8601String()])->values(),
        ];
    }
}
