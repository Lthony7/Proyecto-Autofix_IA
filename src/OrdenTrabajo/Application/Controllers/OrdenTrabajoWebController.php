<?php

namespace Src\OrdenTrabajo\Application\Controllers;

use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Inventario\Infrastructure\Models\OrdenRepuestoEloquentModel;
use Src\Inventario\Infrastructure\Models\RepuestoEloquentModel;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;
use Src\HistorialVehicular\Application\Services\RegistrarEventoVehiculo;
use Src\OrdenTrabajo\Application\Services\ValidarPreparacionTrabajo;
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
use Src\Pago\Application\Services\CalculadorTotalOrden;
use Src\Pago\Infrastructure\Models\PagoEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;

class OrdenTrabajoWebController extends Controller
{
    public function index(Request $request): Response
    {
        $conteos = OrdenTrabajoEloquentModel::query()
            ->visiblePara($request->user())
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $resumenEstados = [
            'cerradas' => (int) ($conteos['finalizada'] ?? 0) + (int) ($conteos['entregada'] ?? 0),
            'enCurso' => (int) ($conteos['en_diagnostico'] ?? 0) + (int) ($conteos['en_reparacion'] ?? 0),
            'pendientes' => (int) ($conteos['pendiente'] ?? 0),
        ];

        $estado=$request->input('estado'); $ordenes=OrdenTrabajoEloquentModel::with(['cliente:id,razon_social','vehiculo:id,placa,marca,modelo','asignaciones'=>fn($q)=>$q->where('activo',true)->with('mecanico:id,nombres,apellidos')])->visiblePara($request->user())->when($estado,fn($q)=>$q->where('estado',$estado))->latest('recibida_en')->paginate(15)->withQueryString();
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
        $this->autorizarVista($request,$orden);$orden->load(['cliente','vehiculo','servicios','asignaciones'=>fn($q)=>$q->where('activo',true)->with('mecanico'),'diagnosticos'=>fn($q)=>$q->orderByDesc('version'),'cita'=>fn($q)=>$q->with(['especialidad:id,nombre','servicio:id,nombre','mecanico:id,nombres,apellidos','repuestosSolicitados','historial'=>fn($h)=>$h->latest('created_at')])]);
        $catalogoRepuestos=RepuestoEloquentModel::where('estado','activo')->orderBy('nombre')->get(['id','codigo','nombre','unidad','stock_actual','precio_venta']);
        $repuestos=$request->user()->can('inventario.consumir')?$catalogoRepuestos->map(fn($p)=>['label'=>"{$p->codigo} · {$p->nombre} · stock {$p->stock_actual} {$p->unidad}",'value'=>$p->id]):[];
        $usos=OrdenRepuestoEloquentModel::with('repuesto:id,codigo,nombre,unidad')->where('orden_id',$orden->id)->latest('created_at')->get()->map(fn($u)=>['id'=>$u->id,'codigo'=>$u->repuesto->codigo,'nombre'=>$u->repuesto->nombre,'unidad'=>$u->repuesto->unidad,'cantidad'=>$u->cantidad,'precioUnitario'=>$u->precio_unitario,'revertido'=>$u->revertido_en!==null,'createdAt'=>$u->created_at?->toIso8601String()]);
        $requerimientos=OrdenRepuestoRequeridoEloquentModel::where('orden_id',$orden->id)->latest()->get()->map(function($r)use($catalogoRepuestos){$p=$catalogoRepuestos->firstWhere('id',$r->repuesto_id);return['id'=>$r->id,'repuestoId'=>$r->repuesto_id,'origen'=>$r->origen,'descripcion'=>$r->descripcion,'cantidad'=>$r->cantidad,'motivo'=>$r->motivo,'estado'=>$r->estado,'stock'=>$p?->stock_actual,'unidad'=>$p?->unidad,'precio'=>$p?->precio_venta,'disponible'=>$p?(float)$p->stock_actual>=(float)$r->cantidad:false];});
        $puedeVerPagos=$request->user()->can('pagos.ver');$finanzas=$puedeVerPagos?$calculador->calcular($orden->id):null;$pagos=$puedeVerPagos?PagoEloquentModel::where('orden_id',$orden->id)->latest('pagado_en')->get()->map(fn($p)=>['id'=>$p->id,'numero'=>$p->numero,'comprobante'=>$p->comprobante_numero,'monto'=>$p->monto,'metodo'=>$p->metodo,'referencia'=>$p->referencia,'estado'=>$p->estado,'pagadoEn'=>$p->pagado_en->toIso8601String(),'motivoAnulacion'=>$p->motivo_anulacion]):[];
        $factura=($request->user()->can('facturas.ver')||$request->user()->can('facturas.crear'))?FacturaOrdenEloquentModel::where('orden_id',$orden->id)->where('estado','emitida')->first(['id','numero','total','emitida_en']):null;
        $consultaIa=\Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel::with(['especialidad:id,nombre','mecanicoSugerido:id,nombres,apellidos','revisiones'])->where('orden_id',$orden->id)->latest()->first();
        $respuestaIa=$consultaIa?->revisiones->where('estado_nuevo','modificada')->last()?->respuesta_ajustada??$consultaIa?->respuesta_original;
        $revisionIa=$consultaIa?->revisiones->last();
        $avances=OrdenAvanceEloquentModel::with(['autor:id,name','servicio:id,nombre_servicio'])->where('orden_id',$orden->id)->when($request->user()->hasRole('Cliente'),fn($q)=>$q->where('visibilidad','cliente'))->latest('created_at')->get()->map(fn($avance)=>['id'=>$avance->id,'descripcion'=>$avance->descripcion,'visibilidad'=>$avance->visibilidad,'estadoOrden'=>$avance->estado_orden,'autor'=>$avance->autor?->name,'servicio'=>$avance->servicio?->nombre_servicio,'createdAt'=>$avance->created_at?->toIso8601String()]);
        $cita=$orden->cita?['id'=>$orden->cita->id,'numero'=>$orden->cita->numero,'estado'=>$orden->cita->estado,'inicio'=>$orden->cita->inicio->toIso8601String(),'fin'=>$orden->cita->fin->toIso8601String(),'atendidaEn'=>$orden->cita->atendida_en?->toIso8601String(),'motivo'=>$orden->cita->motivo,'kilometraje'=>$orden->cita->kilometraje,'especialidad'=>$orden->cita->especialidad?->nombre,'servicioSolicitado'=>$orden->cita->servicio?->nombre,'mecanicoSolicitado'=>$orden->cita->mecanico?trim("{$orden->cita->mecanico->nombres} {$orden->cita->mecanico->apellidos}"):null,'repuestosSolicitados'=>$orden->cita->repuestosSolicitados->map(fn($r)=>['descripcion'=>$r->descripcion,'cantidad'=>$r->cantidad,'observaciones'=>$r->observaciones]),'historial'=>$orden->cita->historial->map(fn($h)=>['estadoAnterior'=>$h->estado_anterior,'estadoNuevo'=>$h->estado_nuevo,'observaciones'=>$h->observaciones,'fecha'=>$h->created_at?->toIso8601String()])]:null;
        $diagnosticoIa=$consultaIa?['id'=>$consultaIa->id,'estado'=>$consultaIa->estado,'entrada'=>$consultaIa->entrada,'respuesta'=>$consultaIa->estado==='descartada'?null:$respuestaIa,'especialidad'=>$consultaIa->especialidad?->nombre,'mecanico'=>$consultaIa->mecanicoSugerido?trim("{$consultaIa->mecanicoSugerido->nombres} {$consultaIa->mecanicoSugerido->apellidos}"):null,'revisada'=>in_array($consultaIa->estado,['confirmada','modificada'],true),'revision'=>$revisionIa?['estado'=>$revisionIa->estado_nuevo,'observacionesCliente'=>$revisionIa->observaciones_cliente?:$revisionIa->observaciones,'motivoDiferencia'=>$revisionIa->motivo_diferencia,'pruebasRealizadas'=>collect($revisionIa->pruebas_realizadas??[])->pluck('descripcion')->filter()->values(),'notasInternas'=>$request->user()->can('ia.revisar')?$revisionIa->notas_internas:null,'fecha'=>$revisionIa->created_at?->toIso8601String()]:null]:null;
        return Inertia::render('OrdenTrabajo/show',['orden'=>$this->detalle($orden),'cita'=>$cita,'mecanicos'=>$request->user()->can('ordenes.asignar')?MecanicoEloquentModel::where('estado','activo')->orderBy('apellidos')->get()->map(fn($m)=>['label'=>"{$m->nombres} {$m->apellidos}",'value'=>$m->id]):[],'serviciosCatalogo'=>ServicioEloquentModel::where('estado','activo')->orderBy('nombre')->get()->map(fn($s)=>['label'=>"{$s->nombre} · $ {$s->precio_base}",'value'=>$s->id]),'repuestosCatalogo'=>$catalogoRepuestos->map(fn($p)=>['id'=>$p->id,'label'=>"{$p->codigo} · {$p->nombre}",'stock'=>$p->stock_actual,'unidad'=>$p->unidad,'precio'=>$p->precio_venta]),'repuestos'=>$repuestos,'repuestosRequeridos'=>$requerimientos,'repuestosUsados'=>$usos,'avances'=>$avances,'finanzas'=>$finanzas,'pagos'=>$pagos,'factura'=>$factura?['id'=>$factura->id,'numero'=>$factura->numero,'total'=>$factura->total,'emitidaEn'=>$factura->emitida_en->toIso8601String()]:null,'diagnosticoIa'=>$diagnosticoIa]);
    }

    public function asignar(AsignarMecanicosRequest $request,OrdenTrabajoEloquentModel $orden,RegistrarAuditoria $auditoria,RegistrarEventoVehiculo $historial): RedirectResponse
    {
        DB::transaction(function()use($request,$orden){$bloqueada=OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();if(in_array($bloqueada->estado,['entregada','cancelada'],true))throw ValidationException::withMessages(['mecanico_ids'=>'No se pueden cambiar mecánicos en una orden cerrada.']);$bloqueada->asignaciones()->where('activo',true)->update(['activo'=>false,'retirado_en'=>now()]);foreach($request->validated('mecanico_ids')as$id)OrdenMecanicoEloquentModel::create(['orden_id'=>$bloqueada->id,'mecanico_id'=>$id,'activo'=>true,'asignado_por'=>$request->user()->id,'observaciones'=>$request->validated('observaciones')]);});
        $mecanicos=MecanicoEloquentModel::whereIn('id',$request->validated('mecanico_ids'))->get()->map(fn($m)=>trim("{$m->nombres} {$m->apellidos}"))->values()->all();$auditoria->registrar('orden.mecanicos_asignados','orden_trabajo',$orden->id,['mecanicos'=>$request->validated('mecanico_ids')],$request);$historial->registrar($orden->vehiculo_id,'orden.mecanicos_asignados',"Se actualizaron los mecánicos de la orden {$orden->numero}.",['mecanicos'=>$mecanicos,'orden_id'=>$orden->id],$request);return back()->with('success','Mecánicos asignados.');
    }

    public function cambiarEstado(CambiarEstadoOrdenRequest $request,OrdenTrabajoEloquentModel $orden,CalculadorTotalOrden $calculador,ValidarPreparacionTrabajo $preparacion,RegistrarAuditoria $auditoria,RegistrarEventoVehiculo $historial): RedirectResponse
    {
        $this->autorizarMecanicoAsignado($request,$orden);$nuevo=$request->validated('estado');
        if($nuevo==='en_diagnostico'&&$orden->cita_id&&!CitaEloquentModel::whereKey($orden->cita_id)->where('estado','atendida')->exists())throw ValidationException::withMessages(['estado'=>'Primero registra la llegada del vehículo marcando la cita como atendida.']);
        if($nuevo==='en_reparacion')$preparacion->validar($orden->id);
        DB::transaction(function()use($request,$orden,$nuevo,$calculador){$bloqueada=OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();$map=['pendiente'=>['en_diagnostico','cancelada'],'en_diagnostico'=>['en_reparacion','cancelada'],'en_reparacion'=>['finalizada','cancelada'],'finalizada'=>['entregada'],'entregada'=>[],'cancelada'=>[]];if(!in_array($nuevo,$map[$bloqueada->estado]??[],true))throw ValidationException::withMessages(['estado'=>"Transición inválida de {$bloqueada->estado} a {$nuevo}."]);if($nuevo==='finalizada'){if(OrdenServicioEloquentModel::where('orden_id',$bloqueada->id)->whereIn('estado',['pendiente','en_proceso'])->exists())throw ValidationException::withMessages(['estado'=>'Completa o cancela todos los servicios antes de finalizar la orden.']);$servicios=OrdenServicioEloquentModel::where('orden_id',$bloqueada->id)->where('estado','completado')->exists();$repuestos=OrdenRepuestoEloquentModel::where('orden_id',$bloqueada->id)->whereNull('revertido_en')->exists();if(!$servicios&&!$repuestos)throw ValidationException::withMessages(['estado'=>'La orden debe conservar al menos un servicio completado o repuesto utilizado.']);}if($nuevo==='cancelada'){if(PagoEloquentModel::where('orden_id',$bloqueada->id)->where('estado','registrado')->exists())throw ValidationException::withMessages(['estado'=>'Primero anula los pagos registrados antes de cancelar la orden.']);if(OrdenRepuestoEloquentModel::where('orden_id',$bloqueada->id)->whereNull('revertido_en')->exists())throw ValidationException::withMessages(['estado'=>'Primero revierte los repuestos utilizados antes de cancelar la orden.']);}if($nuevo==='entregada'){if(!FacturaOrdenEloquentModel::where('orden_id',$bloqueada->id)->where('estado','emitida')->exists())throw ValidationException::withMessages(['estado'=>'Emite la factura definitiva antes de entregar el vehículo.']);$resumen=$calculador->calcular($bloqueada->id);if(!BigDecimal::of($resumen['saldo'])->isZero())throw ValidationException::withMessages(['estado'=>'La orden debe estar totalmente pagada antes de la entrega.']);}$anterior=$bloqueada->estado;$c=['estado'=>$nuevo,'actualizado_por'=>$request->user()->id];if($nuevo==='finalizada')$c['finalizada_en']=now();if($nuevo==='entregada')$c['entregada_en']=now();if($nuevo==='cancelada')$c=[...$c,'motivo_cancelacion'=>$request->validated('motivo'),'cancelada_en'=>now(),'cancelada_por'=>$request->user()->id];$bloqueada->update($c);OrdenEstadoHistorialEloquentModel::create(['orden_id'=>$bloqueada->id,'estado_anterior'=>$anterior,'estado_nuevo'=>$nuevo,'observaciones'=>$request->validated('observaciones')?:$request->validated('motivo'),'usuario_id'=>$request->user()->id]);});
        $accion=$nuevo==='finalizada'?'servicio.finalizado':'orden.estado_cambiado';$descripcion=$nuevo==='finalizada'?"Se finalizaron los servicios de la orden {$orden->numero}.":"La orden {$orden->numero} cambió al estado {$nuevo}.";$auditoria->registrar('orden.estado_cambiado','orden_trabajo',$orden->id,['estado'=>$nuevo],$request);$historial->registrar($orden->vehiculo_id,$accion,$descripcion,['orden_id'=>$orden->id,'estado'=>$nuevo],$request);return back()->with('success','Estado de la orden actualizado.');
    }

    public function diagnosticar(RegistrarDiagnosticoRequest $request,OrdenTrabajoEloquentModel $orden,RegistrarAuditoria $auditoria,RegistrarEventoVehiculo $historial): RedirectResponse
    {
        $this->autorizarMecanicoAsignado($request,$orden);if(!in_array($orden->estado,['en_diagnostico','en_reparacion'],true))throw ValidationException::withMessages(['diagnostico'=>'La orden debe estar en diagnóstico o reparación.']);
        DB::transaction(function()use($request,$orden){$orden->diagnosticos()->where('vigente',true)->update(['vigente'=>false]);$version=((int)$orden->diagnosticos()->max('version'))+1;$mecanico=MecanicoEloquentModel::where('usuario_id',$request->user()->id)->value('id');DiagnosticoTecnicoEloquentModel::create(['orden_id'=>$orden->id,'mecanico_id'=>$mecanico,'version'=>$version,'diagnostico'=>$request->validated('diagnostico'),'pruebas_realizadas'=>$request->validated('pruebasRealizadas'),'recomendaciones'=>$request->validated('recomendaciones'),'vigente'=>true,'registrado_por'=>$request->user()->id]);});
        $auditoria->registrar('diagnostico.registrado','orden_trabajo',$orden->id,[],$request);$historial->registrar($orden->vehiculo_id,'diagnostico.registrado',"Se registró un diagnóstico técnico en la orden {$orden->numero}.",['orden_id'=>$orden->id],$request);return back()->with('success','Diagnóstico técnico registrado correctamente. Puedes continuar con la reparación y documentar los avances.');
    }

    public function cambiarEstadoServicio(Request$request,OrdenTrabajoEloquentModel$orden,OrdenServicioEloquentModel$servicio,ValidarPreparacionTrabajo$preparacion,RegistrarAuditoria$auditoria,RegistrarEventoVehiculo$historial):RedirectResponse
    {
        if($request->input('estado')==='completado')$preparacion->validar($orden->id);
        abort_unless($request->user()->can('ordenes.avanzar'),403);$this->autorizarMecanicoAsignado($request,$orden);abort_unless($servicio->orden_id===$orden->id,404);
        $d=$request->validate(['estado'=>'required|in:en_proceso,completado,cancelado','observaciones'=>'nullable|string','trabajoRealizado'=>[$request->input('estado')==='completado'?'required':'nullable','string']]);
        DB::transaction(function()use($request,$orden,$servicio,$d){$bloqueada=OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();if(!in_array($bloqueada->estado,['en_diagnostico','en_reparacion'],true))throw ValidationException::withMessages(['servicio'=>'Los servicios solo cambian durante diagnóstico o reparación.']);$linea=OrdenServicioEloquentModel::whereKey($servicio->id)->lockForUpdate()->firstOrFail();$map=['pendiente'=>['en_proceso','completado','cancelado'],'en_proceso'=>['completado','cancelado'],'completado'=>[],'cancelado'=>[]];if(!in_array($d['estado'],$map[$linea->estado]??[],true))throw ValidationException::withMessages(['servicio'=>"Transición inválida de {$linea->estado} a {$d['estado']}."]);$anterior=$linea->estado;$linea->update(['estado'=>$d['estado'],'observaciones'=>$d['observaciones']??null,'trabajo_realizado'=>$d['trabajoRealizado']??$linea->trabajo_realizado,'completado_en'=>$d['estado']==='completado'?now():null,'completado_por'=>$d['estado']==='completado'?$request->user()->id:null]);$request->attributes->set('servicio_estado_anterior',$anterior);});
        $auditoria->registrar('orden.servicio_estado_cambiado','orden_servicio',$servicio->id,['anterior'=>$request->attributes->get('servicio_estado_anterior'),'nuevo'=>$d['estado'],'trabajo_realizado'=>$d['trabajoRealizado']??null],$request);if($d['estado']==='completado'){$servicio->refresh();$historial->registrar($orden->vehiculo_id,'servicio.finalizado',"Se completó {$servicio->nombre_servicio} en la orden {$orden->numero}.",['orden_id'=>$orden->id,'servicio_id'=>$servicio->id,'trabajo_realizado'=>$servicio->trabajo_realizado],$request);}return back()->with('success','Estado del servicio actualizado.');
    }

    public function agregarServicio(Request $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria): RedirectResponse
    {
        abort_unless($request->user()->can('ordenes.avanzar'),403);$this->autorizarMecanicoAsignado($request,$orden);
        $d=$request->validate(['servicioId'=>['required','uuid',\Illuminate\Validation\Rule::exists('servicios_taller','id')->where('estado','activo')],'motivo'=>'required|string']);
        if(!in_array($orden->estado,['en_diagnostico','en_reparacion'],true))throw ValidationException::withMessages(['servicioId'=>'La orden debe estar en diagnóstico o reparación.']);
        $catalogo=ServicioEloquentModel::findOrFail($d['servicioId']);$existente=OrdenServicioEloquentModel::where('orden_id',$orden->id)->where('servicio_id',$catalogo->id)->first();
        if($existente&&$existente->estado!=='cancelado')throw ValidationException::withMessages(['servicioId'=>'Este servicio ya está incluido en la orden.']);
        if($existente)$existente->update(['estado'=>'pendiente','observaciones'=>$d['motivo'],'origen'=>'mecanico','trabajo_realizado'=>null,'agregado_por'=>$request->user()->id]);else OrdenServicioEloquentModel::create(['orden_id'=>$orden->id,'servicio_id'=>$catalogo->id,'nombre_servicio'=>$catalogo->nombre,'precio_acordado'=>$catalogo->precio_base,'estado'=>'pendiente','observaciones'=>$d['motivo'],'origen'=>'mecanico','agregado_por'=>$request->user()->id]);
        $auditoria->registrar('orden.servicio_agregado','orden_trabajo',$orden->id,['servicio_id'=>$catalogo->id,'motivo'=>$d['motivo']],$request);return back()->with('success','Servicio requerido agregado a la orden.');
    }

    public function agregarRepuestoRequerido(Request $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria): RedirectResponse
    {
        abort_unless($request->user()->can('ordenes.avanzar'),403);$this->autorizarMecanicoAsignado($request,$orden);
        $d=$request->validate(['repuestoId'=>['nullable','uuid',\Illuminate\Validation\Rule::exists('repuestos','id')->where('estado','activo')],'descripcion'=>'required|string','cantidad'=>'required|numeric|gt:0|max:9999','motivo'=>'required|string']);
        if(!in_array($orden->estado,['en_diagnostico','en_reparacion'],true))throw ValidationException::withMessages(['repuestoId'=>'La orden debe estar en diagnóstico o reparación.']);
        $requerimiento=OrdenRepuestoRequeridoEloquentModel::create(['orden_id'=>$orden->id,'repuesto_id'=>$d['repuestoId']??null,'origen'=>'mecanico','descripcion'=>$d['descripcion'],'cantidad'=>$d['cantidad'],'motivo'=>$d['motivo'],'estado'=>'requerido','agregado_por'=>$request->user()->id]);
        $auditoria->registrar('orden.repuesto_requerido','orden_repuesto_requerido',$requerimiento->id,[],$request);return back()->with('success','Repuesto requerido agregado sin descontar inventario.');
    }

    public function retirarRepuestoRequerido(Request $request, OrdenTrabajoEloquentModel $orden, OrdenRepuestoRequeridoEloquentModel $requerimiento, RegistrarAuditoria $auditoria): RedirectResponse
    {
        abort_unless($request->user()->can('ordenes.avanzar'),403);$this->autorizarMecanicoAsignado($request,$orden);abort_unless($requerimiento->orden_id===$orden->id,404);
        $d=$request->validate(['motivo'=>'required|string']);if($requerimiento->estado==='retirado')throw ValidationException::withMessages(['repuesto'=>'El requerimiento ya fue retirado.']);if(OrdenRepuestoEloquentModel::where('requerimiento_id',$requerimiento->id)->whereNull('revertido_en')->exists())throw ValidationException::withMessages(['repuesto'=>'Primero revierte el repuesto utilizado.']);
        $requerimiento->update(['estado'=>'retirado','retirado_en'=>now(),'retirado_por'=>$request->user()->id,'motivo_retiro'=>$d['motivo']]);$auditoria->registrar('orden.repuesto_requerido_retirado','orden_repuesto_requerido',$requerimiento->id,['motivo'=>$d['motivo']],$request);return back()->with('success','Requerimiento retirado sin afectar inventario.');
    }

    public function registrarAvance(Request $request, OrdenTrabajoEloquentModel $orden, RegistrarAuditoria $auditoria, RegistrarEventoVehiculo $historial): RedirectResponse
    {
        abort_unless($request->user()->can('ordenes.avanzar'), 403);
        $this->autorizarMecanicoAsignado($request, $orden);
        if (! in_array($orden->estado, ['en_diagnostico', 'en_reparacion'], true)) throw ValidationException::withMessages(['avance' => 'Los avances solo pueden registrarse durante diagnóstico o reparación.']);
        $datos = $request->validate(['descripcion' => ['required', 'string'], 'visibilidad' => ['required', 'in:cliente,interno'], 'servicioId' => ['nullable', 'uuid']]);
        if (! empty($datos['servicioId']) && ! OrdenServicioEloquentModel::whereKey($datos['servicioId'])->where('orden_id', $orden->id)->exists()) throw ValidationException::withMessages(['servicioId' => 'El servicio seleccionado no pertenece a esta orden.']);
        $avance = OrdenAvanceEloquentModel::create(['orden_id' => $orden->id, 'servicio_id' => $datos['servicioId'] ?? null, 'descripcion' => $datos['descripcion'], 'visibilidad' => $datos['visibilidad'], 'estado_orden' => $orden->estado, 'registrado_por' => $request->user()->id]);
        $auditoria->registrar('orden.avance_registrado', 'orden_avance', $avance->id, ['orden_id' => $orden->id, 'visibilidad' => $avance->visibilidad], $request);
        $historial->registrar($orden->vehiculo_id, 'orden.avance_registrado', "Se registró un avance en la orden {$orden->numero}.", ['orden_id' => $orden->id, 'avance_id' => $avance->id, 'visibilidad' => $avance->visibilidad], $request);
        return back()->with('success', 'Avance registrado.');
    }

    private function crearOrden(string$cliente,string$vehiculo,string$falla,?int$km,array$servicios,array$mecanicos,string$usuario): OrdenTrabajoEloquentModel
    {
        $orden=OrdenTrabajoEloquentModel::create(['numero'=>'OT-'.now()->format('Ymd').'-'.mb_strtoupper(substr(str_replace('-','',(string)str()->uuid()),0,6)),'cliente_id'=>$cliente,'vehiculo_id'=>$vehiculo,'falla_reportada'=>$falla,'kilometraje'=>$km,'estado'=>'pendiente','creado_por'=>$usuario,'actualizado_por'=>$usuario]);
        foreach(ServicioEloquentModel::whereIn('id',$servicios)->get()as$s)OrdenServicioEloquentModel::create(['orden_id'=>$orden->id,'servicio_id'=>$s->id,'nombre_servicio'=>$s->nombre,'precio_acordado'=>$s->precio_base,'estado'=>'pendiente']);foreach($mecanicos as$id)OrdenMecanicoEloquentModel::create(['orden_id'=>$orden->id,'mecanico_id'=>$id,'activo'=>true,'asignado_por'=>$usuario]);OrdenEstadoHistorialEloquentModel::create(['orden_id'=>$orden->id,'estado_nuevo'=>'pendiente','observaciones'=>'Orden creada','usuario_id'=>$usuario]);return $orden;
    }
    private function catalogos():array{return['clientes'=>ClienteEloquentModel::with(['vehiculos'=>fn($q)=>$q->where('estado','activo')])->where('estado','activo')->orderBy('razon_social')->get()->map(fn($c)=>['id'=>$c->id,'nombre'=>$c->razon_social,'vehiculos'=>$c->vehiculos->map(fn($v)=>['id'=>$v->id,'label'=>"{$v->placa} · {$v->marca} {$v->modelo}"])]),'servicios'=>ServicioEloquentModel::where('estado','activo')->orderBy('nombre')->get()->map(fn($s)=>['label'=>"{$s->nombre} · $ {$s->precio_base}",'value'=>$s->id]),'mecanicos'=>MecanicoEloquentModel::where('estado','activo')->orderBy('apellidos')->get()->map(fn($m)=>['label'=>"{$m->nombres} {$m->apellidos}",'value'=>$m->id])];}
    private function autorizarVista(Request$r,OrdenTrabajoEloquentModel$o):void{abort_unless(OrdenTrabajoEloquentModel::whereKey($o->id)->visiblePara($r->user())->exists(),403);}
    private function autorizarMecanicoAsignado(Request$r,OrdenTrabajoEloquentModel$o):void{if($r->user()->hasRole('Mecánico'))abort_unless($o->asignaciones()->where('activo',true)->whereHas('mecanico',fn($q)=>$q->where('usuario_id',$r->user()->id))->exists(),403);}
    private function resumen($o):array{return['id'=>$o->id,'numero'=>$o->numero,'cliente'=>$o->cliente?->razon_social,'vehiculo'=>$o->vehiculo?->placa,'estado'=>$o->estado,'recibidaEn'=>$o->recibida_en->toIso8601String(),'mecanicos'=>$o->asignaciones->map(fn($a)=>$a->mecanico?->nombres.' '.$a->mecanico?->apellidos)->filter()->values()];}
    private function detalle($o):array{return[...$this->resumen($o),'fallaReportada'=>$o->falla_reportada,'kilometraje'=>$o->kilometraje,'servicios'=>$o->servicios->map(fn($s)=>['id'=>$s->id,'nombre'=>$s->nombre_servicio,'precio'=>$s->precio_acordado,'estado'=>$s->estado,'observaciones'=>$s->observaciones,'origen'=>$s->origen,'trabajoRealizado'=>$s->trabajo_realizado]),'mecanicoIds'=>$o->asignaciones->pluck('mecanico_id'),'diagnosticos'=>$o->diagnosticos->map(fn($d)=>['id'=>$d->id,'version'=>$d->version,'diagnostico'=>$d->diagnostico,'pruebasRealizadas'=>$d->pruebas_realizadas,'recomendaciones'=>$d->recomendaciones,'vigente'=>$d->vigente,'createdAt'=>$d->created_at?->toIso8601String()])];}
}
