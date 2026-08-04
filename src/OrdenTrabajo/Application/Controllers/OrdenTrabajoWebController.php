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
use Src\OrdenTrabajo\Infrastructure\Models\OrdenEstadoHistorialEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenMecanicoEloquentModel;
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
        $this->autorizarVista($request,$orden);$orden->load(['cliente','vehiculo','servicios','asignaciones'=>fn($q)=>$q->where('activo',true)->with('mecanico'),'diagnosticos'=>fn($q)=>$q->orderByDesc('version')]);
        $repuestos=$request->user()->can('inventario.consumir')?RepuestoEloquentModel::where('estado','activo')->where('stock_actual','>',0)->orderBy('nombre')->get()->map(fn($p)=>['label'=>"{$p->codigo} · {$p->nombre} ({$p->stock_actual} {$p->unidad})",'value'=>$p->id]):[];
        $usos=OrdenRepuestoEloquentModel::with('repuesto:id,codigo,nombre,unidad')->where('orden_id',$orden->id)->latest('created_at')->get()->map(fn($u)=>['id'=>$u->id,'codigo'=>$u->repuesto->codigo,'nombre'=>$u->repuesto->nombre,'unidad'=>$u->repuesto->unidad,'cantidad'=>$u->cantidad,'precioUnitario'=>$u->precio_unitario,'revertido'=>$u->revertido_en!==null,'createdAt'=>$u->created_at?->toIso8601String()]);
        $puedeVerPagos=$request->user()->can('pagos.ver');$finanzas=$puedeVerPagos?$calculador->calcular($orden->id):null;$pagos=$puedeVerPagos?PagoEloquentModel::where('orden_id',$orden->id)->latest('pagado_en')->get()->map(fn($p)=>['id'=>$p->id,'numero'=>$p->numero,'comprobante'=>$p->comprobante_numero,'monto'=>$p->monto,'metodo'=>$p->metodo,'referencia'=>$p->referencia,'estado'=>$p->estado,'pagadoEn'=>$p->pagado_en->toIso8601String(),'motivoAnulacion'=>$p->motivo_anulacion]):[];
        $factura=($request->user()->can('facturas.ver')||$request->user()->can('facturas.crear'))?FacturaOrdenEloquentModel::where('orden_id',$orden->id)->where('estado','emitida')->first(['id','numero','total','emitida_en']):null;
        $consultaIa=\Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel::with(['especialidad:id,nombre','mecanicoSugerido:id,nombres,apellidos','revisiones'])->where('orden_id',$orden->id)->latest()->first();
        $respuestaIa=$consultaIa?->revisiones->where('estado_nuevo','modificada')->last()?->respuesta_ajustada??$consultaIa?->respuesta_original;
        return Inertia::render('OrdenTrabajo/show',['orden'=>$this->detalle($orden),'mecanicos'=>$request->user()->can('ordenes.asignar')?MecanicoEloquentModel::where('estado','activo')->orderBy('apellidos')->get()->map(fn($m)=>['label'=>"{$m->nombres} {$m->apellidos}",'value'=>$m->id]):[],'repuestos'=>$repuestos,'repuestosUsados'=>$usos,'finanzas'=>$finanzas,'pagos'=>$pagos,'factura'=>$factura?['id'=>$factura->id,'numero'=>$factura->numero,'total'=>$factura->total,'emitidaEn'=>$factura->emitida_en->toIso8601String()]:null,'diagnosticoIa'=>$consultaIa?['id'=>$consultaIa->id,'estado'=>$consultaIa->estado,'resumen'=>$consultaIa->estado==='descartada'?null:($respuestaIa['resumen_cliente']??$respuestaIa['resumen']??null),'riesgo'=>$consultaIa->estado==='descartada'?null:($respuestaIa['nivel_riesgo']??$consultaIa->nivel_riesgo),'circulacion'=>$consultaIa->estado==='descartada'?null:($respuestaIa['puede_circular']??$consultaIa->puede_circular_ia),'especialidad'=>$consultaIa->especialidad?->nombre,'mecanico'=>$consultaIa->mecanicoSugerido?trim("{$consultaIa->mecanicoSugerido->nombres} {$consultaIa->mecanicoSugerido->apellidos}"):null,'revisada'=>in_array($consultaIa->estado,['confirmada','modificada'],true)]:null]);
    }

    public function asignar(AsignarMecanicosRequest $request,OrdenTrabajoEloquentModel $orden,RegistrarAuditoria $auditoria,RegistrarEventoVehiculo $historial): RedirectResponse
    {
        DB::transaction(function()use($request,$orden){$bloqueada=OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();if(in_array($bloqueada->estado,['entregada','cancelada'],true))throw ValidationException::withMessages(['mecanico_ids'=>'No se pueden cambiar mecánicos en una orden cerrada.']);$bloqueada->asignaciones()->where('activo',true)->update(['activo'=>false,'retirado_en'=>now()]);foreach($request->validated('mecanico_ids')as$id)OrdenMecanicoEloquentModel::create(['orden_id'=>$bloqueada->id,'mecanico_id'=>$id,'activo'=>true,'asignado_por'=>$request->user()->id,'observaciones'=>$request->validated('observaciones')]);});
        $mecanicos=MecanicoEloquentModel::whereIn('id',$request->validated('mecanico_ids'))->get()->map(fn($m)=>trim("{$m->nombres} {$m->apellidos}"))->values()->all();$auditoria->registrar('orden.mecanicos_asignados','orden_trabajo',$orden->id,['mecanicos'=>$request->validated('mecanico_ids')],$request);$historial->registrar($orden->vehiculo_id,'orden.mecanicos_asignados',"Se actualizaron los mecánicos de la orden {$orden->numero}.",['mecanicos'=>$mecanicos,'orden_id'=>$orden->id],$request);return back()->with('success','Mecánicos asignados.');
    }

    public function cambiarEstado(CambiarEstadoOrdenRequest $request,OrdenTrabajoEloquentModel $orden,CalculadorTotalOrden $calculador,ValidarPreparacionTrabajo $preparacion,RegistrarAuditoria $auditoria,RegistrarEventoVehiculo $historial): RedirectResponse
    {
        $this->autorizarMecanicoAsignado($request,$orden);$nuevo=$request->validated('estado');
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
        abort_unless($request->user()->can('ordenes.avanzar'),403);$this->autorizarMecanicoAsignado($request,$orden);abort_unless($servicio->orden_id===$orden->id,404);$d=$request->validate(['estado'=>'required|in:en_proceso,completado,cancelado','observaciones'=>'nullable|string|max:1000']);DB::transaction(function()use($request,$orden,$servicio,$d){$bloqueada=OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();if(!in_array($bloqueada->estado,['en_diagnostico','en_reparacion'],true))throw ValidationException::withMessages(['servicio'=>'Los servicios solo cambian durante diagnóstico o reparación.']);$linea=OrdenServicioEloquentModel::whereKey($servicio->id)->lockForUpdate()->firstOrFail();$map=['pendiente'=>['en_proceso','completado','cancelado'],'en_proceso'=>['completado','cancelado'],'completado'=>[],'cancelado'=>[]];if(!in_array($d['estado'],$map[$linea->estado]??[],true))throw ValidationException::withMessages(['servicio'=>"Transición inválida de {$linea->estado} a {$d['estado']}."]);$anterior=$linea->estado;$linea->update(['estado'=>$d['estado'],'observaciones'=>$d['observaciones']??null]);$request->attributes->set('servicio_estado_anterior',$anterior);});$auditoria->registrar('orden.servicio_estado_cambiado','orden_servicio',$servicio->id,['anterior'=>$request->attributes->get('servicio_estado_anterior'),'nuevo'=>$d['estado']],$request);if($d['estado']==='completado'){$servicio->refresh();$historial->registrar($orden->vehiculo_id,'servicio.finalizado',"Se completó {$servicio->nombre_servicio} en la orden {$orden->numero}.",['orden_id'=>$orden->id,'servicio_id'=>$servicio->id,'observaciones'=>$servicio->observaciones],$request);}return back()->with('success','Estado del servicio actualizado.');
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
    private function detalle($o):array{return[...$this->resumen($o),'fallaReportada'=>$o->falla_reportada,'kilometraje'=>$o->kilometraje,'servicios'=>$o->servicios->map(fn($s)=>['id'=>$s->id,'nombre'=>$s->nombre_servicio,'precio'=>$s->precio_acordado,'estado'=>$s->estado,'observaciones'=>$s->observaciones]),'mecanicoIds'=>$o->asignaciones->pluck('mecanico_id'),'diagnosticos'=>$o->diagnosticos->map(fn($d)=>['id'=>$d->id,'version'=>$d->version,'diagnostico'=>$d->diagnostico,'pruebasRealizadas'=>$d->pruebas_realizadas,'recomendaciones'=>$d->recomendaciones,'vigente'=>$d->vigente,'createdAt'=>$d->created_at?->toIso8601String()])];}
}
