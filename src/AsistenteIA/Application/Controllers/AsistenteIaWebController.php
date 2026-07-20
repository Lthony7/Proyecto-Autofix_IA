<?php

namespace Src\AsistenteIA\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Src\AsistenteIA\Application\Services\GeneradorDiagnosticoInicial;
use Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel;
use Src\AsistenteIA\Infrastructure\Models\ConsumoIaEloquentModel;
use Src\AsistenteIA\Infrastructure\Models\RevisionSugerenciaIaEloquentModel;
use Src\AsistenteIA\Infrastructure\Requests\RevisarSugerenciaIaRequest;
use Src\AsistenteIA\Infrastructure\Requests\SolicitarDiagnosticoIaRequest;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class AsistenteIaWebController extends Controller
{
    public function index(Request $request): Response
    {
        $consultas=ConsultaIaEloquentModel::with(['vehiculo:id,placa,marca,modelo','cliente:id,razon_social'])->visiblePara($request->user())->latest()->paginate(15);
        $consultas->through(fn($c)=>['id'=>$c->id,'cliente'=>$c->cliente?->razon_social,'vehiculo'=>$c->vehiculo?->placa.' · '.$c->vehiculo?->marca.' '.$c->vehiculo?->modelo,'estado'=>$c->estado,'prioridad'=>$c->prioridad,'simulada'=>$c->simulada,'createdAt'=>$c->created_at->toIso8601String()]);
        return Inertia::render('AsistenteIA/index',['consultas'=>$consultas]);
    }

    public function create(): Response
    {
        $clientes=ClienteEloquentModel::with(['vehiculos'=>fn($q)=>$q->where('estado','activo')])->where('estado','activo')->orderBy('razon_social')->get();
        return Inertia::render('AsistenteIA/form',['clientes'=>$clientes->map(fn($c)=>['id'=>$c->id,'nombre'=>$c->razon_social,'vehiculos'=>$c->vehiculos->map(fn($v)=>['id'=>$v->id,'label'=>"{$v->placa} · {$v->marca} {$v->modelo} ({$v->anio})"])]),'categorias'=>['frenos','motor','electrico','suspension','transmision','climatizacion','otro']]);
    }

    public function store(SolicitarDiagnosticoIaRequest $request,GeneradorDiagnosticoInicial $generador,RegistrarAuditoria $auditoria): RedirectResponse
    {
        $validado=$request->validated();$vehiculo=VehiculoEloquentModel::findOrFail($validado['vehiculo_id']);
        $entrada=[...collect($validado)->except(['cliente_id','vehiculo_id'])->all(),'vehiculo'=>['marca'=>$vehiculo->marca,'modelo'=>$vehiculo->modelo,'anio'=>$vehiculo->anio,'combustible'=>$vehiculo->combustible]];
        $hash=hash('sha256',json_encode($entrada,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION));
        $reciente=ConsultaIaEloquentModel::where('solicitada_por',$request->user()->id)->where('vehiculo_id',$vehiculo->id)->where('entrada_hash',$hash)->where('created_at','>=',now()->subDay())->latest()->first();
        if($reciente)return redirect()->route('ia.show',$reciente)->with('success','Se reutilizó una orientación reciente con los mismos datos.');

        $generado=$generador->generar($entrada);$respuesta=$generado['respuesta'];$meta=$generado['meta'];
        $especialidad=EspecialidadEloquentModel::where('estado','activo')->where('nombre','ilike','%'.$respuesta['especialidad_recomendada'].'%')->first();
        $consulta=DB::transaction(function()use($request,$validado,$entrada,$hash,$respuesta,$meta,$especialidad){
            $c=ConsultaIaEloquentModel::create(['cliente_id'=>$validado['cliente_id'],'vehiculo_id'=>$validado['vehiculo_id'],'solicitada_por'=>$request->user()->id,'entrada'=>$entrada,'entrada_hash'=>$hash,'respuesta_original'=>$respuesta,'proveedor'=>$meta['proveedor'],'modelo'=>$meta['modelo'],'simulada'=>$meta['simulada'],'estado'=>'generada','prioridad'=>$respuesta['prioridad'],'especialidad_sugerida_id'=>$especialidad?->id]);
            ConsumoIaEloquentModel::create(['consulta_id'=>$c->id,'usuario_id'=>$request->user()->id,'proveedor'=>$meta['proveedor'],'modelo'=>$meta['modelo'],'resultado'=>$meta['resultado'],'latencia_ms'=>$meta['latencia_ms'],'tokens_entrada'=>$meta['tokens_entrada'],'tokens_salida'=>$meta['tokens_salida']]);return$c;
        });
        $auditoria->registrar('ia.sugerencia_generada','consulta_ia',$consulta->id,['simulada'=>$consulta->simulada],$request);return redirect()->route('ia.show',$consulta);
    }

    public function show(Request $request,ConsultaIaEloquentModel $consulta): Response
    {
        abort_unless(ConsultaIaEloquentModel::whereKey($consulta->id)->visiblePara($request->user())->exists()||$consulta->solicitada_por===$request->user()->id,403);
        $consulta->load(['cliente:id,razon_social','vehiculo:id,placa,marca,modelo','especialidad:id,nombre','revisiones']);
        $servicios=ServicioEloquentModel::where('estado','activo')->when($consulta->especialidad_sugerida_id,fn($q)=>$q->where('especialidad_id',$consulta->especialidad_sugerida_id))->get(['id','nombre']);
        return Inertia::render('AsistenteIA/show',['consulta'=>['id'=>$consulta->id,'cliente'=>$consulta->cliente?->razon_social,'vehiculo'=>"{$consulta->vehiculo?->placa} · {$consulta->vehiculo?->marca} {$consulta->vehiculo?->modelo}",'entrada'=>$consulta->entrada,'respuesta'=>$consulta->respuesta_original,'estado'=>$consulta->estado,'prioridad'=>$consulta->prioridad,'simulada'=>$consulta->simulada,'especialidad'=>$consulta->especialidad?->nombre,'especialidadId'=>$consulta->especialidad_sugerida_id,'citaId'=>$consulta->cita_id,'ordenId'=>$consulta->orden_id,'revisiones'=>$consulta->revisiones],'servicios'=>$servicios]);
    }

    public function revisar(RevisarSugerenciaIaRequest $request,ConsultaIaEloquentModel $consulta,RegistrarAuditoria $auditoria): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Administrador')||ConsultaIaEloquentModel::whereKey($consulta->id)->visiblePara($request->user())->exists(),403);
        $nuevo=$request->validated('estado');$permitidas=['generada'=>['en_revision','confirmada','modificada','descartada'],'en_revision'=>['confirmada','modificada','descartada'],'confirmada'=>[],'modificada'=>[],'descartada'=>[],'cerrada'=>[]];if(!in_array($nuevo,$permitidas[$consulta->estado]??[],true))throw ValidationException::withMessages(['estado'=>'La revisión solicitada no es válida.']);
        $ajustada=null;if($nuevo==='modificada'){$ajustada=$consulta->respuesta_original;$ajustada['resumen']=$request->validated('resumenAjustado');$ajustada['observaciones_mecanico']=$request->validated('observaciones');}
        DB::transaction(function()use($request,$consulta,$nuevo,$ajustada){$anterior=$consulta->estado;$consulta->update(['estado'=>$nuevo]);RevisionSugerenciaIaEloquentModel::create(['consulta_id'=>$consulta->id,'estado_anterior'=>$anterior,'estado_nuevo'=>$nuevo,'respuesta_ajustada'=>$ajustada,'observaciones'=>$request->validated('observaciones'),'mecanico_id'=>MecanicoEloquentModel::where('usuario_id',$request->user()->id)->value('id'),'revisada_por'=>$request->user()->id]);});
        $auditoria->registrar('ia.sugerencia_revisada','consulta_ia',$consulta->id,['estado'=>$nuevo],$request);return back()->with('success','Revisión registrada sin alterar la respuesta original.');
    }
}
