<?php

namespace Src\AsistenteIA\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Src\AsistenteIA\Application\Services\GeneradorDiagnosticoInicial;
use Src\AsistenteIA\Application\Services\SelectorMecanicoDiagnostico;
use Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel;
use Src\AsistenteIA\Infrastructure\Models\ConsumoIaEloquentModel;
use Src\AsistenteIA\Infrastructure\Models\RevisionSugerenciaIaEloquentModel;
use Src\AsistenteIA\Infrastructure\Requests\RevisarSugerenciaIaRequest;
use Src\AsistenteIA\Infrastructure\Requests\SolicitarDiagnosticoIaRequest;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class AsistenteIaWebController extends Controller
{
    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->input('buscar'));
        $estado = $request->input('estado');
        $prioridad = $request->input('prioridad');
        $simulada = $request->input('simulada');
        $porPagina = in_array((int) $request->input('porPagina'), [10, 25, 50, 100], true) ? (int) $request->input('porPagina') : 10;

        $base = ConsultaIaEloquentModel::query()->visiblePara($request->user());
        $consultas = (clone $base)->with(['vehiculo:id,placa,marca,modelo', 'cliente:id,razon_social', 'especialidad:id,nombre', 'mecanicoSugerido:id,nombres,apellidos', 'orden:id,numero,estado'])
            ->when($buscar, fn ($q) => $q->where(fn ($s) => $s->whereHas('cliente', fn ($c) => $c->where('razon_social', 'ilike', "%{$buscar}%"))->orWhereHas('vehiculo', fn ($v) => $v->where('placa', 'ilike', "%{$buscar}%")->orWhere('marca', 'ilike', "%{$buscar}%")->orWhere('modelo', 'ilike', "%{$buscar}%"))->orWhereHas('orden', fn ($o) => $o->where('numero', 'ilike', "%{$buscar}%"))))
            ->when($estado, fn ($q) => $q->where('estado', $estado))->when($prioridad, fn ($q) => $q->where('prioridad', $prioridad))
            ->when($simulada !== null && $simulada !== '', fn ($q) => $q->where('simulada', filter_var($simulada, FILTER_VALIDATE_BOOLEAN)))
            ->latest()->paginate($porPagina)->withQueryString();
        $consultas->through(fn ($c) => [
            'id' => $c->id, 'orden' => $c->orden?->numero, 'estadoOrden' => $c->orden?->estado,
            'cliente' => $c->cliente?->razon_social, 'vehiculo' => trim($c->vehiculo?->placa.' · '.$c->vehiculo?->marca.' '.$c->vehiculo?->modelo),
            'estado' => $c->estado, 'prioridad' => $c->prioridad, 'riesgo' => $c->nivel_riesgo,
            'especialidad' => $c->especialidad?->nombre, 'mecanico' => $c->mecanicoSugerido ? trim("{$c->mecanicoSugerido->nombres} {$c->mecanicoSugerido->apellidos}") : null,
            'simulada' => $c->simulada, 'createdAt' => $c->created_at->toIso8601String(), 'updatedAt' => $c->updated_at->toIso8601String(),
        ]);

        return Inertia::render('AsistenteIA/index', [
            'consultas' => $consultas,
            'filtros' => ['buscar' => $buscar, 'estado' => $estado ?: 'todos', 'prioridad' => $prioridad ?: 'todas', 'simulada' => $simulada === null ? 'todas' : $simulada, 'porPagina' => $porPagina],
            'stats' => ['total' => (clone $base)->count(), 'pendientes' => (clone $base)->whereIn('estado', ['generada', 'en_revision'])->count(), 'confirmadas' => (clone $base)->where('estado', 'confirmada')->count(), 'modificadas' => (clone $base)->where('estado', 'modificada')->count()],
        ]);
    }

    public function create(Request $request): Response
    {
        $alcanceVehiculos = function ($q) use ($request) {
            $q->where('estado', 'activo');
            if ($request->user()->hasRole('Mecánico')) {
                $q->where(fn ($alcance) => $alcance
                    ->whereHas('ordenes', fn ($o) => $o->whereIn('estado', ['pendiente','asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba'])->whereHas('asignaciones', fn ($a) => $a->where('activo', true)->whereHas('mecanico', fn ($m) => $m->where('usuario_id', $request->user()->id))))
                    ->orWhereHas('citas', fn ($c) => $c->whereIn('estado', ['pendiente', 'confirmada', 'reprogramada'])->where('fin', '>=', now())->whereHas('mecanico', fn ($m) => $m->where('usuario_id', $request->user()->id))));
            }
        };
        $clientes = ClienteEloquentModel::with(['vehiculos' => $alcanceVehiculos])->where('estado', 'activo');
        if ($request->user()->hasRole('Mecánico')) {
            $clientes->whereHas('vehiculos', $alcanceVehiculos);
        }

        return Inertia::render('AsistenteIA/form', [
            'clientes' => $clientes->orderBy('razon_social')->get()->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->razon_social, 'vehiculos' => $c->vehiculos->map(fn ($v) => ['id' => $v->id, 'label' => "{$v->placa} · {$v->marca} {$v->modelo} ({$v->anio})", 'kilometraje' => $v->kilometraje, 'detalle' => ['marca' => $v->marca, 'modelo' => $v->modelo, 'anio' => $v->anio, 'combustible' => $v->combustible, 'color' => $v->color]])]),
            'categorias' => ['frenos', 'motor', 'electrico', 'suspension', 'transmision', 'climatizacion', 'otro'],
        ]);
    }

    public function store(SolicitarDiagnosticoIaRequest $request, GeneradorDiagnosticoInicial $generador, SelectorMecanicoDiagnostico $selector, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $validado = $request->validated();
        $vehiculo = VehiculoEloquentModel::findOrFail($validado['vehiculo_id']);
        $entrada = [...collect($validado)->except(['cliente_id', 'vehiculo_id'])->all(), ...$this->contextoVehiculo($vehiculo, $request->user())];
        $hash = hash('sha256', json_encode($entrada, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        $lock = Cache::lock("ia:generar:{$request->user()->id}:{$vehiculo->id}:{$hash}", max(30, (int) config('services.groq.timeout', 15) + 10));
        if (! $lock->get()) throw ValidationException::withMessages(['sintomaPrincipal' => 'Ya se está generando un diagnóstico con estos datos. Espera unos segundos antes de reintentar.']);
        try {
        $reciente = ConsultaIaEloquentModel::where('solicitada_por', $request->user()->id)->where('vehiculo_id', $vehiculo->id)->where('entrada_hash', $hash)->where('created_at', '>=', now()->subDay())->latest()->first();
        if ($reciente && ! in_array($reciente->estado, ['descartada', 'cerrada'], true) && $reciente->prompt_version === GeneradorDiagnosticoInicial::PROMPT_VERSION && $reciente->esquema_version === GeneradorDiagnosticoInicial::SCHEMA_VERSION) return redirect()->route('ia.show', $reciente)->with('success', 'Se reutilizó un diagnóstico preliminar reciente con los mismos datos.');

        $generado = $generador->generar($entrada);
        $respuesta = $generado['respuesta'];
        $meta = $generado['meta'];
        $especialidad = $this->resolverEspecialidad($respuesta['especialidad_requerida'], $validado['categoria_falla']);
        $mecanicos = $selector->candidatos($especialidad);
        $mecanicoSugerido = $mecanicos->first();

        $consulta = DB::transaction(function () use ($request, $validado, $entrada, $hash, $respuesta, $generado, $meta, $especialidad, $mecanicoSugerido) {
            $consulta = ConsultaIaEloquentModel::create([
                'cliente_id' => $validado['cliente_id'], 'vehiculo_id' => $validado['vehiculo_id'], 'solicitada_por' => $request->user()->id,
                'entrada' => $entrada, 'entrada_hash' => $hash, 'version' => 1, 'prompt_version' => $meta['prompt_version'], 'esquema_version' => $meta['esquema_version'],
                'respuesta_original' => $respuesta, 'respuesta_cruda' => $generado['raw'], 'meta_generacion' => collect($meta)->except(['codigo_error'])->all(),
                'proveedor' => $meta['proveedor'], 'modelo' => $meta['modelo'], 'simulada' => $meta['simulada'], 'estado' => 'generada', 'prioridad' => $respuesta['prioridad'],
                'nivel_confianza' => $respuesta['nivel_confianza'], 'nivel_riesgo' => $respuesta['nivel_riesgo'], 'nivel_urgencia' => $respuesta['nivel_urgencia'],
                'puede_circular_ia' => $respuesta['puede_circular'], 'complejidad' => $respuesta['complejidad'],
                'tiempo_estimado_diagnostico' => $respuesta['tiempo_estimado_diagnostico'], 'tiempo_estimado_reparacion' => $respuesta['tiempo_estimado_reparacion'],
                'especialidad_sugerida_id' => $especialidad?->id, 'mecanico_sugerido_id' => $mecanicoSugerido?->id,
            ]);
            ConsumoIaEloquentModel::create([
                'consulta_id' => $consulta->id, 'usuario_id' => $request->user()->id, 'proveedor' => $meta['proveedor'], 'proveedor_intentado' => $meta['proveedor_intentado'],
                'modelo' => $meta['modelo'], 'modelo_intentado' => $meta['modelo_intentado'], 'resultado' => $meta['resultado'], 'latencia_ms' => $meta['latencia_ms'],
                'tokens_entrada' => $meta['tokens_entrada'], 'tokens_salida' => $meta['tokens_salida'], 'codigo_error' => $meta['codigo_error'],
                'meta' => ['prompt_version' => $meta['prompt_version'], 'esquema_version' => $meta['esquema_version'], 'finish_reason' => $meta['finish_reason']],
            ]);
            return $consulta;
        });
        } finally {
            $lock->release();
        }

        $auditoria->registrar('ia.sugerencia_generada', 'consulta_ia', $consulta->id, ['simulada' => $consulta->simulada, 'prompt_version' => $consulta->prompt_version, 'mecanico_sugerido_id' => $consulta->mecanico_sugerido_id], $request);
        return redirect()->route('ia.show', $consulta)->with('success', 'Diagnóstico IA preliminar generado. Debe ser revisado y confirmado por el mecánico asignado.');
    }

    public function show(Request $request, ConsultaIaEloquentModel $consulta, SelectorMecanicoDiagnostico $selector): Response
    {
        abort_unless(ConsultaIaEloquentModel::whereKey($consulta->id)->visiblePara($request->user())->exists() || $consulta->solicitada_por === $request->user()->id, 403);
        $consulta->load(['cliente:id,razon_social', 'vehiculo:id,placa,marca,modelo,anio,kilometraje', 'especialidad:id,nombre', 'mecanicoSugerido:id,nombres,apellidos,telefono', 'revisiones', 'orden:id,numero,estado']);
        $puedeRevisar = $this->puedeRevisar($request, $consulta);
        $ultimaModificacion = $consulta->revisiones->where('estado_nuevo', 'modificada')->last();
        $respuestaVigente = $ultimaModificacion?->respuesta_ajustada ?: $consulta->respuesta_original;
        $mecanicos = $puedeRevisar ? $selector->candidatos($consulta->especialidad)->map(fn ($m, $indice) => [
            'id' => $m->id, 'nombre' => trim("{$m->nombres} {$m->apellidos}"),
            'especialidades' => $m->especialidades->pluck('nombre')->values(), 'ordenesActivas' => $m->ordenes_activas,
            'citasFuturas' => $m->citas_futuras, 'tieneHorario' => $m->horarios_activos > 0,
            'preferente' => $m->id === $consulta->mecanico_sugerido_id || ($indice === 0 && ! $consulta->mecanico_sugerido_id),
        ]) : collect($consulta->mecanicoSugerido ? [[
            'id' => $consulta->mecanicoSugerido->id, 'nombre' => trim("{$consulta->mecanicoSugerido->nombres} {$consulta->mecanicoSugerido->apellidos}"),
            'especialidades' => array_filter([$consulta->especialidad?->nombre]), 'preferente' => true,
        ]] : []);

        return Inertia::render('AsistenteIA/show', [
            'consulta' => [
                'id' => $consulta->id, 'version' => $consulta->version, 'cliente' => $consulta->cliente?->razon_social,
                'vehiculo' => "{$consulta->vehiculo?->placa} · {$consulta->vehiculo?->marca} {$consulta->vehiculo?->modelo}",
                'vehiculoDetalle' => ['placa' => $consulta->vehiculo?->placa, 'marca' => $consulta->vehiculo?->marca, 'modelo' => $consulta->vehiculo?->modelo, 'anio' => $consulta->vehiculo?->anio, 'kilometraje' => $consulta->entrada['kilometraje'] ?? $consulta->vehiculo?->kilometraje],
                'entrada' => $consulta->entrada, 'respuesta' => $consulta->respuesta_original, 'respuestaVigente' => $respuestaVigente,
                'estado' => $consulta->estado, 'prioridad' => $consulta->prioridad, 'riesgo' => $consulta->nivel_riesgo, 'urgencia' => $consulta->nivel_urgencia,
                'simulada' => $consulta->simulada, 'especialidad' => $consulta->especialidad?->nombre, 'especialidadId' => $consulta->especialidad_sugerida_id,
                'mecanicoSugeridoId' => $consulta->mecanico_sugerido_id, 'citaId' => $consulta->cita_id, 'ordenId' => $consulta->orden_id,
                'ordenNumero' => $consulta->orden?->numero, 'revisiones' => $consulta->revisiones->map(fn ($revision) => [
                    'version' => $revision->version, 'estado' => $revision->estado_nuevo,
                    'observacionesCliente' => $revision->observaciones_cliente ?: $revision->observaciones,
                    'createdAt' => $revision->created_at?->toIso8601String(),
                ])->values(),
                'promptVersion' => $consulta->prompt_version, 'esquemaVersion' => $consulta->esquema_version,
            ],
            'mecanicos' => $mecanicos,
            'puedeRevisar' => $puedeRevisar,
        ]);
    }

    public function revisar(RevisarSugerenciaIaRequest $request, ConsultaIaEloquentModel $consulta, RegistrarAuditoria $auditoria): RedirectResponse
    {
        abort_unless($this->puedeRevisar($request, $consulta), 403);
        $nuevo = $request->validated('estado');

        DB::transaction(function () use ($request, $consulta, $nuevo) {
            $bloqueada = ConsultaIaEloquentModel::whereKey($consulta->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->puedeRevisar($request, $bloqueada), 403);
            $permitidas = ['generada' => ['en_revision', 'confirmada', 'modificada', 'descartada'], 'en_revision' => ['confirmada', 'modificada', 'descartada'], 'confirmada' => [], 'modificada' => [], 'descartada' => [], 'cerrada' => []];
            if (! in_array($nuevo, $permitidas[$bloqueada->estado] ?? [], true)) throw ValidationException::withMessages(['estado' => 'La revisión solicitada ya no es válida para el estado actual.']);

            $ajustada = null;
            if ($nuevo === 'modificada') {
                $ajustada = $bloqueada->respuesta_original;
                $ajustada['diagnostico_tecnico'] = $request->validated('diagnosticoCorregido');
                $ajustada['resumen_cliente'] = $request->validated('resumenAjustado') ?: $ajustada['resumen_cliente'];
                $ajustada['resumen'] = $ajustada['resumen_cliente'];
                if ($request->validated('servicioCorregido')) $ajustada['servicios_sugeridos'] = [$request->validated('servicioCorregido')];
                if ($request->validated('prioridadCorregida')) $ajustada['prioridad'] = $request->validated('prioridadCorregida');
            }

            $version = (int) RevisionSugerenciaIaEloquentModel::where('consulta_id', $bloqueada->id)->max('version') + 1;
            RevisionSugerenciaIaEloquentModel::create([
                'consulta_id' => $bloqueada->id, 'version' => $version, 'estado_anterior' => $bloqueada->estado, 'estado_nuevo' => $nuevo,
                'coincide_ia' => $request->validated('coincideIa'), 'respuesta_ajustada' => $ajustada,
                'observaciones' => $request->validated('observacionesCliente') ?: $request->validated('observaciones'),
                'observaciones_cliente' => $request->validated('observacionesCliente'), 'notas_internas' => $request->validated('notasInternas'),
                'motivo_diferencia' => $request->validated('motivoDiferencia'),
                'pruebas_realizadas' => $request->validated('pruebasRealizadas') ? [['descripcion' => $request->validated('pruebasRealizadas')]] : [],
                'mecanico_id' => MecanicoEloquentModel::where('usuario_id', $request->user()->id)->value('id'), 'revisada_por' => $request->user()->id,
            ]);
            $bloqueada->update(['estado' => $nuevo, 'prioridad' => $ajustada['prioridad'] ?? $bloqueada->prioridad]);
        });

        $auditoria->registrar('ia.sugerencia_revisada', 'consulta_ia', $consulta->id, ['estado' => $nuevo, 'coincide_ia' => $request->validated('coincideIa')], $request);
        return back()->with('success', 'Revisión técnica registrada sin alterar la respuesta original de la IA.');
    }

    private function contextoVehiculo(VehiculoEloquentModel $vehiculo, $usuario): array
    {
        $historial = OrdenTrabajoEloquentModel::where('vehiculo_id', $vehiculo->id)
            ->visiblePara($usuario)
            ->with(['servicios:id,orden_id,nombre_servicio,estado', 'diagnosticos' => fn ($q) => $q->publicadoActual()->select('id', 'orden_id', 'diagnostico', 'recomendaciones', 'created_at')])
            ->latest('recibida_en')->limit(5)->get(['id', 'numero', 'estado', 'falla_reportada', 'kilometraje', 'recibida_en'])
            ->map(fn ($orden) => ['numero' => $orden->numero, 'fecha' => $orden->recibida_en?->toDateString(), 'estado' => $orden->estado, 'falla' => $orden->falla_reportada, 'kilometraje' => $orden->kilometraje, 'servicios' => $orden->servicios->pluck('nombre_servicio'), 'diagnostico_confirmado' => $orden->diagnosticos->first()?->diagnostico]);

        return [
            'vehiculo' => ['marca' => $vehiculo->marca, 'modelo' => $vehiculo->modelo, 'anio' => $vehiculo->anio, 'combustible' => $vehiculo->combustible, 'kilometraje_registrado' => $vehiculo->kilometraje],
            'historial_tecnico_relevante' => $historial,
            'catalogos_autorizados' => [
                'especialidades' => EspecialidadEloquentModel::where('estado', 'activo')->orderBy('nombre')->pluck('nombre')->take(30)->values(),
                'servicios' => ServicioEloquentModel::where('estado', 'activo')->orderBy('nombre')->pluck('nombre')->take(60)->values(),
            ],
        ];
    }

    private function resolverEspecialidad(string $sugerida, string $categoria): ?EspecialidadEloquentModel
    {
        $especialidades = EspecialidadEloquentModel::where('estado', 'activo')->get();
        $objetivo = Str::lower(Str::ascii($sugerida));
        $coincidencia = $especialidades->first(fn ($e) => str_contains($objetivo, Str::lower(Str::ascii($e->nombre))) || str_contains(Str::lower(Str::ascii($e->nombre)), $objetivo));
        if ($coincidencia) return $coincidencia;

        $terminos = ['frenos' => 'freno', 'motor' => 'motor', 'electrico' => 'electr', 'suspension' => 'suspension', 'transmision' => 'transmision', 'climatizacion' => 'clima'];
        $termino = $terminos[$categoria] ?? null;
        return $termino ? $especialidades->first(fn ($e) => str_contains(Str::lower(Str::ascii($e->nombre)), $termino)) : null;
    }

    private function puedeRevisar(Request $request, ConsultaIaEloquentModel $consulta): bool
    {
        if (! $request->user()->can('ia.revisar')) return false;
        if ($request->user()->hasRole('Administrador')) return true;
        $mecanicoId = MecanicoEloquentModel::where('usuario_id', $request->user()->id)->value('id');
        if (! $mecanicoId) return false;
        if ($consulta->cita_id && \Src\Cita\Infrastructure\Models\CitaEloquentModel::whereKey($consulta->cita_id)->where('mecanico_id', $mecanicoId)->exists()) return true;
        if ($consulta->orden_id && OrdenTrabajoEloquentModel::whereKey($consulta->orden_id)->whereHas('asignaciones', fn ($q) => $q->where('activo', true)->where('mecanico_id', $mecanicoId))->exists()) return true;
        return ! $consulta->cita_id && ! $consulta->orden_id && $consulta->mecanico_sugerido_id === $mecanicoId;
    }
}
