<?php

namespace Src\Cita\Application\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel;
use Src\Cita\Application\Services\ValidadorDisponibilidad;
use Src\Cita\Application\Services\GeneradorSlotsDisponibles;
use Src\Cita\Application\Services\VencerCitasFinalizadas;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;
use Src\Cita\Infrastructure\Models\CitaEstadoHistorialEloquentModel;
use Src\Cita\Infrastructure\Models\CitaRepuestoSolicitadoEloquentModel;
use Src\Cita\Infrastructure\Requests\CambiarEstadoCitaRequest;
use Src\Cita\Infrastructure\Requests\GuardarCitaRequest;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Inventario\Infrastructure\Models\RepuestoEloquentModel;
use Src\OrdenTrabajo\Application\Services\CrearOrdenDesdeCita;
use Src\OrdenTrabajo\Application\Services\CerrarRecursosOrdenCancelada;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenEstadoHistorialEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenMecanicoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Taller\Infrastructure\Models\DisponibilidadMecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class CitaWebController extends Controller
{
    public function index(Request $request, VencerCitasFinalizadas $vencer): Response
    {
        $vencer->ejecutar();
        $estado = $request->input('estado');
        $citas = CitaEloquentModel::with(['cliente:id,razon_social', 'vehiculo:id,placa,marca,modelo', 'servicio:id,nombre', 'mecanico:id,nombres,apellidos', 'orden:id,cita_id,numero'])
            ->visiblePara($request->user())->when(in_array($estado, ['pendiente', 'confirmada', 'reprogramada', 'atendida', 'cancelada', 'vencida'], true), fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('inicio')->paginate(15)->withQueryString();
        $citas->through(fn ($c) => $this->toArray($c));
        return Inertia::render('Cita/index', ['citas' => $citas, 'estado' => $estado]);
    }

    public function calendario(Request $request, VencerCitasFinalizadas $vencer): Response
    {
        $vencer->ejecutar();
        $filtrosValidados = $request->validate([
            'mecanico' => ['nullable', 'uuid', Rule::exists('mecanicos', 'id')->where('estado', 'activo')],
            'estado' => ['nullable', Rule::in(['pendiente', 'confirmada', 'reprogramada', 'atendida', 'cancelada', 'vencida'])],
        ]);
        $vista = in_array($request->input('vista'), ['dia', 'semana', 'mes'], true) ? $request->input('vista') : 'semana';
        $fechaSolicitada = (string) $request->input('fecha', now()->format('Y-m-d'));
        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $fechaSolicitada);
            if (! $fecha || $fecha->format('Y-m-d') !== $fechaSolicitada) throw new \InvalidArgumentException;
            $fecha = $fecha->startOfDay();
        } catch (\Throwable) {
            $fecha = CarbonImmutable::now()->startOfDay();
        }

        [$inicio, $fin] = match ($vista) {
            'dia' => [$fecha, $fecha->endOfDay()],
            'mes' => [$fecha->startOfMonth(), $fecha->endOfMonth()],
            default => [$fecha->startOfWeek(), $fecha->endOfWeek()],
        };

        $mecanicoId = $filtrosValidados['mecanico'] ?? null;
        $estado = $filtrosValidados['estado'] ?? null;
        $estados = ['pendiente', 'confirmada', 'reprogramada', 'atendida', 'cancelada', 'vencida'];
        $citas = CitaEloquentModel::with(['cliente:id,razon_social', 'vehiculo:id,placa,marca,modelo', 'servicio:id,nombre', 'mecanico:id,nombres,apellidos', 'orden:id,cita_id,numero'])
            ->visiblePara($request->user())
            ->where('inicio', '<=', $fin)->where('fin', '>=', $inicio)
            ->when($mecanicoId, fn ($q) => $q->where('mecanico_id', $mecanicoId))
            ->when(in_array($estado, $estados, true), fn ($q) => $q->where('estado', $estado))
            ->orderBy('inicio')
            ->get()
            ->map(fn ($c) => $this->toArray($c));

        $mecanicosModelos = MecanicoEloquentModel::where('estado', 'activo')
            ->when($request->user()->hasRole('Cliente'), fn ($q) => $q->whereHas('citas', fn ($c) => $c->visiblePara($request->user())))
            ->orderBy('apellidos')
            ->get(['id', 'nombres', 'apellidos']);
        $mecanicos = $mecanicosModelos->map(fn ($m) => ['label' => trim("{$m->nombres} {$m->apellidos}"), 'value' => $m->id]);
        $disponibilidades = DisponibilidadMecanicoEloquentModel::whereIn('mecanico_id', $mecanicosModelos->pluck('id'))
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNull('vigente_desde')->orWhereDate('vigente_desde', '<=', $fin->toDateString()))
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $inicio->toDateString()))
            ->orderBy('hora_inicio')->get()->map(fn ($h) => [
                'mecanicoId' => $h->mecanico_id, 'dia' => $h->dia_semana,
                'inicio' => substr($h->hora_inicio, 0, 5), 'fin' => substr($h->hora_fin, 0, 5),
                'vigenteDesde' => $h->vigente_desde?->toDateString(), 'vigenteHasta' => $h->vigente_hasta?->toDateString(),
            ]);

        return Inertia::render('Cita/calendar', [
            'citas' => $citas,
            'mecanicos' => $mecanicos,
            'disponibilidades' => $disponibilidades,
            'vista' => $vista,
            'fecha' => $fecha->format('Y-m-d'),
            'inicioPeriodo' => $inicio->format('Y-m-d'),
            'finPeriodo' => $fin->format('Y-m-d'),
            'filtros' => ['mecanico' => $mecanicoId ?: 'todos', 'estado' => in_array($estado, $estados, true) ? $estado : 'todos'],
        ]);
    }

    public function create(Request $request): Response
    {
        $consultaIa = $request->filled('consultaIa') ? ConsultaIaEloquentModel::with(['revisiones', 'especialidad:id,nombre', 'mecanicoSugerido:id,nombres,apellidos'])->whereKey($request->input('consultaIa'))->visiblePara($request->user())->whereNull('cita_id')->whereNotIn('estado', ['descartada', 'cerrada'])->first() : null;
        $ultimaModificacion = $consultaIa?->revisiones->where('estado_nuevo', 'modificada')->last();
        $respuestaIa = $ultimaModificacion?->respuesta_ajustada ?: $consultaIa?->respuesta_original;
        $servicioIa = $consultaIa ? $this->resolverServicioIa($respuestaIa ?? [], $consultaIa->especialidad_sugerida_id) : null;
        $clientes = ClienteEloquentModel::with(['vehiculos' => fn ($q) => $q->where('estado', 'activo')->orderBy('placa')])->where('estado', 'activo')
            ->when($request->user()->hasRole('Cliente'), fn ($q) => $q->where('usuario_id', $request->user()->id))->orderBy('razon_social')->get();
        $mecanicos = MecanicoEloquentModel::with(['especialidades' => fn ($q) => $q->wherePivot('activo', true), 'disponibilidades' => fn ($q) => $q->where('activo', true)])->where('estado', 'activo')->orderBy('apellidos')->get();
        $horizonte = now()->addDays(90)->endOfDay();
        $ocupaciones = CitaEloquentModel::whereIn('mecanico_id', $mecanicos->pluck('id'))->whereNotIn('estado', ['cancelada', 'vencida'])
            ->where('fin', '>', now())->where('inicio', '<=', $horizonte)->orderBy('inicio')->get(['mecanico_id', 'inicio', 'fin'])
            ->map(fn ($c) => ['mecanicoId' => $c->mecanico_id, 'fecha' => $c->inicio->toDateString(), 'horaInicio' => $c->inicio->format('H:i'), 'horaFin' => $c->fin->format('H:i')]);
        return Inertia::render('Cita/form', [
            'clientes' => $clientes->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->razon_social, 'vehiculos' => $c->vehiculos->map(fn ($v) => ['id' => $v->id, 'label' => "{$v->placa} · {$v->marca} {$v->modelo}"])]),
            'especialidades' => EspecialidadEloquentModel::where('estado', 'activo')->orderBy('nombre')->get(['id', 'nombre']),
            'servicios' => ServicioEloquentModel::where('estado', 'activo')->orderBy('nombre')->get(['id', 'especialidad_id', 'nombre', 'duracion_minutos', 'precio_base']),
            'repuestos' => RepuestoEloquentModel::where('estado', 'activo')->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'unidad', 'stock_actual', 'precio_venta']),
            'mecanicos' => $mecanicos->map(fn ($m) => ['id' => $m->id, 'nombre' => "{$m->nombres} {$m->apellidos}", 'especialidadIds' => $m->especialidades->pluck('id'), 'horarios' => $m->disponibilidades->map(fn ($h) => ['dia' => $h->dia_semana, 'inicio' => substr($h->hora_inicio, 0, 5), 'fin' => substr($h->hora_fin, 0, 5), 'vigenteDesde' => $h->vigente_desde?->toDateString(), 'vigenteHasta' => $h->vigente_hasta?->toDateString()])]),
            'ocupaciones' => $ocupaciones,
            'horizonteDias' => 90,
            'prefill' => $consultaIa ? [
                'consultaIaId' => $consultaIa->id,
                'clienteId' => $consultaIa->cliente_id,
                'vehiculoId' => $consultaIa->vehiculo_id,
                'especialidadId' => $consultaIa->especialidad_sugerida_id,
                'mecanicoId' => $consultaIa->mecanico_sugerido_id,
                'servicioId' => $servicioIa?->id,
                'kilometraje' => $consultaIa->entrada['kilometraje'] ?? null,
                'motivo' => $this->resumenReporteIa($consultaIa->entrada),
                'contextoIa' => [
                    'estado' => $consultaIa->estado,
                    'entrada' => collect($consultaIa->entrada)->only(['categoria_falla', 'sintoma_principal', 'momento_ocurre', 'frecuencia', 'tiempo_desde_inicio', 'intensidad', 'condiciones', 'senales', 'luces_tablero', 'perdida_potencia_arranque', 'codigos_obd', 'pruebas_realizadas', 'puede_circular', 'urgencia_percibida', 'reparaciones_recientes', 'observaciones'])->all(),
                    'resumen' => $respuestaIa['resumen_cliente'] ?? $respuestaIa['resumen'] ?? null,
                    'causas' => $respuestaIa['posibles_causas'] ?? [],
                    'acciones' => $respuestaIa['acciones_recomendadas'] ?? [],
                    'pruebas' => $respuestaIa['pruebas_sugeridas'] ?? [],
                    'servicios' => $respuestaIa['servicios_sugeridos'] ?? [],
                    'repuestos' => $respuestaIa['repuestos_posibles'] ?? [],
                    'especialidad' => $consultaIa->especialidad?->nombre ?? $respuestaIa['especialidad_requerida'] ?? null,
                    'mecanico' => $consultaIa->mecanicoSugerido ? trim("{$consultaIa->mecanicoSugerido->nombres} {$consultaIa->mecanicoSugerido->apellidos}") : null,
                    'prioridad' => $respuestaIa['prioridad'] ?? $consultaIa->prioridad,
                    'riesgo' => $respuestaIa['nivel_riesgo'] ?? $consultaIa->nivel_riesgo,
                    'circulacion' => $respuestaIa['puede_circular'] ?? $consultaIa->puede_circular_ia,
                    'servicioCoincidente' => $servicioIa?->nombre,
                ],
            ] : null,
        ]);
    }

    public function store(GuardarCitaRequest $request, ValidadorDisponibilidad $disponibilidad, CrearOrdenDesdeCita $crearOrden, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $datos = $request->validated();
        $cita = DB::transaction(function () use ($datos, $request, $disponibilidad, $crearOrden) {
            $servicio = ! empty($datos['servicio_id']) ? ServicioEloquentModel::find($datos['servicio_id']) : null;
            $inicio = CarbonImmutable::parse($datos['inicio']); $fin = $inicio->addMinutes($servicio?->duracion_minutos ?? 60);
            if (! empty($datos['mecanico_id'])) {
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ["cita:{$datos['mecanico_id']}:{$inicio->toDateString()}"]);
                $disponibilidad->validar($datos['mecanico_id'], $inicio, $fin);
            }
            $consultaIaId = $datos['consulta_ia_id'] ?? null;
            if ($consultaIaId) {
                $consultaIa = ConsultaIaEloquentModel::whereKey($consultaIaId)->whereNull('cita_id')->whereNotIn('estado', ['descartada', 'cerrada'])->lockForUpdate()->first();
                if (! $consultaIa) throw ValidationException::withMessages(['consultaIaId' => 'El diagnóstico IA ya fue vinculado o no está disponible.']);
            }
            $cita = CitaEloquentModel::create([...collect($datos)->except(['inicio','consulta_ia_id','repuestos_solicitados'])->all(), 'numero' => 'CIT-'.$inicio->format('Ymd').'-'.mb_strtoupper(substr(str_replace('-', '', (string) str()->uuid()), 0, 6)), 'especialidad_id' => $servicio?->especialidad_id ?? $datos['especialidad_id'], 'inicio' => $inicio, 'fin' => $fin, 'estado' => 'pendiente', 'creado_por' => $request->user()->id, 'actualizado_por' => $request->user()->id]);
            if ($consultaIaId) $consultaIa->update(['cita_id'=>$cita->id]);
            CitaEstadoHistorialEloquentModel::create(['cita_id' => $cita->id, 'estado_nuevo' => 'pendiente', 'observaciones' => 'Cita creada', 'usuario_id' => $request->user()->id]);
            if ($datos['kilometraje'] ?? null) VehiculoEloquentModel::whereKey($datos['vehiculo_id'])->where('kilometraje', '<', $datos['kilometraje'])->update(['kilometraje' => $datos['kilometraje'], 'actualizado_por' => $request->user()->id]);
            foreach ($datos['repuestos_solicitados'] ?? [] as $item) CitaRepuestoSolicitadoEloquentModel::create([...$item, 'cita_id' => $cita->id, 'solicitado_por' => $request->user()->id]);
            $crearOrden->crear($cita->load('repuestosSolicitados'), $request->user()->id);
            return $cita;
        });
        $auditoria->registrar('cita.creada', 'cita', $cita->id, [], $request);
        return redirect()->route('citas.index')->with('success', 'Cita agendada exitosamente.');
    }

    public function cambiarEstado(CambiarEstadoCitaRequest $request, CitaEloquentModel $cita, ValidadorDisponibilidad $disponibilidad, CerrarRecursosOrdenCancelada $cerrarCancelada, RegistrarAuditoria $auditoria): RedirectResponse
    {
        abort_unless(CitaEloquentModel::whereKey($cita->id)->visiblePara($request->user())->exists(), 403);
        $nuevo = $request->validated('estado');
        DB::transaction(function () use ($request, $cita, $nuevo, $disponibilidad, $cerrarCancelada) {
            $bloqueada = CitaEloquentModel::with('servicio')->whereKey($cita->id)->visiblePara($request->user())->lockForUpdate()->firstOrFail();
            $transiciones = ['pendiente' => ['confirmada', 'reprogramada', 'cancelada'], 'confirmada' => ['reprogramada', 'atendida', 'cancelada'], 'reprogramada' => ['confirmada', 'reprogramada', 'cancelada'], 'vencida' => ['reprogramada', 'atendida', 'cancelada'], 'atendida' => [], 'cancelada' => []];
            if (! in_array($nuevo, $transiciones[$bloqueada->estado] ?? [], true)) throw ValidationException::withMessages(['estado' => "No se puede pasar de {$bloqueada->estado} a {$nuevo}."]);
            $anterior = ['estado' => $bloqueada->estado, 'inicio' => $bloqueada->inicio->toIso8601String(), 'fin' => $bloqueada->fin->toIso8601String(), 'mecanico_id' => $bloqueada->mecanico_id];
            $cambios = ['estado' => $nuevo, 'actualizado_por' => $request->user()->id];
            if ($nuevo === 'reprogramada') {
                $inicio = CarbonImmutable::parse($request->validated('inicio')); $fin = $inicio->addMinutes($bloqueada->servicio?->duracion_minutos ?? $bloqueada->inicio->diffInMinutes($bloqueada->fin));
                $mecanicoId = $request->validated('mecanico_id') ?: $bloqueada->mecanico_id;
                if (! $mecanicoId) throw ValidationException::withMessages(['mecanicoId' => 'Selecciona el mecánico encargado.']);
                if ($bloqueada->especialidad_id && ! MecanicoEloquentModel::whereKey($mecanicoId)->whereHas('especialidades', fn ($q) => $q->where('especialidades.id', $bloqueada->especialidad_id)->where('mecanico_especialidad.activo', true))->exists()) throw ValidationException::withMessages(['mecanicoId' => 'El mecánico no pertenece a la especialidad requerida por la cita.']);
                if ($mecanicoId) { DB::select('select pg_advisory_xact_lock(hashtext(?))', ["cita:{$mecanicoId}:{$inicio->toDateString()}"]); $disponibilidad->validar($mecanicoId, $inicio, $fin, $bloqueada->id); }
                $cambios = [...$cambios, 'inicio' => $inicio, 'fin' => $fin, 'mecanico_id' => $mecanicoId];
            } elseif ($nuevo === 'confirmada') {
                $mecanicoId = $request->validated('mecanico_id') ?: $bloqueada->mecanico_id;
                if (! $mecanicoId) throw ValidationException::withMessages(['mecanicoId' => 'Asigna un mecánico antes de confirmar la cita.']);
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ["cita:{$mecanicoId}:{$bloqueada->inicio->toDateString()}"]); $disponibilidad->validar($mecanicoId, CarbonImmutable::instance($bloqueada->inicio), CarbonImmutable::instance($bloqueada->fin), $bloqueada->id); $cambios['mecanico_id'] = $mecanicoId;
            } elseif ($nuevo === 'atendida') {
                if (! $bloqueada->mecanico_id) throw ValidationException::withMessages(['estado' => 'La cita debe tener un mecánico asignado antes de marcarla como atendida.']);
                if ($bloqueada->inicio->isFuture()) throw ValidationException::withMessages(['estado' => 'No se puede marcar como atendida una cita que todavía no ha comenzado.']);
                $llegada = now();
                $cambios = [...$cambios, 'atendida_en' => $llegada, 'atendida_por' => $request->user()->id];
                OrdenTrabajoEloquentModel::where('cita_id', $bloqueada->id)->where('estado', 'pendiente')->update(['recibida_en' => $llegada, 'actualizado_por' => $request->user()->id]);
            } elseif ($nuevo === 'cancelada') {
                $orden = OrdenTrabajoEloquentModel::where('cita_id', $bloqueada->id)->lockForUpdate()->first();
                if ($orden && $orden->estado !== 'pendiente') throw ValidationException::withMessages(['estado' => 'La orden vinculada ya inició trabajo y la cita no puede cancelarse.']);
                if ($orden) {
                    $orden->update(['estado' => 'cancelada', 'motivo_cancelacion' => $request->validated('motivo'), 'cancelada_en' => now(), 'cancelada_por' => $request->user()->id, 'actualizado_por' => $request->user()->id]);
                    $cerrarCancelada->ejecutar($orden, $request->user()->id);
                    OrdenEstadoHistorialEloquentModel::create(['orden_id' => $orden->id, 'estado_anterior' => 'pendiente', 'estado_nuevo' => 'cancelada', 'observaciones' => 'Cancelada automáticamente con la cita: '.$request->validated('motivo'), 'usuario_id' => $request->user()->id]);
                }
                $cambios = [...$cambios, 'motivo_cancelacion' => $request->validated('motivo'), 'cancelada_en' => now(), 'cancelada_por' => $request->user()->id];
            }
            $estadoAnterior = $bloqueada->estado; $bloqueada->update($cambios);
            if ($nuevo === 'reprogramada') {
                $orden = OrdenTrabajoEloquentModel::where('cita_id', $bloqueada->id)->lockForUpdate()->first();
                if ($orden && $orden->estado === 'pendiente') {
                    $orden->update(['recibida_en' => $inicio, 'actualizado_por' => $request->user()->id]);
                    $orden->asignaciones()->where('activo', true)->update(['activo' => false, 'retirado_en' => now()]);
                    if ($mecanicoId) OrdenMecanicoEloquentModel::create(['orden_id' => $orden->id, 'mecanico_id' => $mecanicoId, 'activo' => true, 'asignado_por' => $request->user()->id, 'observaciones' => 'Asignación actualizada al reprogramar la cita.']);
                }
            }
            CitaEstadoHistorialEloquentModel::create(['cita_id' => $bloqueada->id, 'estado_anterior' => $estadoAnterior, 'estado_nuevo' => $nuevo, 'observaciones' => $request->validated('observaciones') ?: $request->validated('motivo'), 'datos_anteriores' => $anterior, 'usuario_id' => $request->user()->id]);
        });
        $auditoria->registrar('cita.estado_cambiado', 'cita', $cita->id, ['estado' => $nuevo], $request);
        return back()->with('success', 'Cita actualizada exitosamente.');
    }

    public function disponibilidadReprogramacion(Request $request, CitaEloquentModel $cita, GeneradorSlotsDisponibles $generador): JsonResponse
    {
        abort_unless(CitaEloquentModel::whereKey($cita->id)->visiblePara($request->user())->exists(), 403);
        $cita->load('servicio');
        $mecanicos = MecanicoEloquentModel::where('estado', 'activo')
            ->when($cita->especialidad_id, fn ($q) => $q->whereHas('especialidades', fn ($especialidades) => $especialidades->where('especialidades.id', $cita->especialidad_id)->where('mecanico_especialidad.activo', true)))
            ->orderBy('apellidos')->get(['id', 'nombres', 'apellidos']);
        $datos = $request->validate(['mecanicoId' => ['nullable', 'uuid']]);
        $mecanicoSolicitado = $datos['mecanicoId'] ?? null;
        if ($mecanicoSolicitado && ! $mecanicos->contains('id', $mecanicoSolicitado)) throw ValidationException::withMessages(['mecanicoId' => 'El mecánico no pertenece a la especialidad requerida por la cita.']);
        $mecanicoId = $mecanicoSolicitado ?: ($mecanicos->contains('id', $cita->mecanico_id) ? $cita->mecanico_id : null);
        $duracion = $cita->servicio?->duracion_minutos ?? max(1, (int) $cita->inicio->diffInMinutes($cita->fin));

        return response()->json([
            'mecanicos' => $mecanicos->map(fn ($m) => ['label' => trim("{$m->nombres} {$m->apellidos}"), 'value' => $m->id])->values(),
            'mecanicoId' => $mecanicoId,
            'duracionMinutos' => $duracion,
            'fechas' => $mecanicoId ? $generador->generar($mecanicoId, $duracion, $cita->id) : [],
        ]);
    }

    private function toArray(CitaEloquentModel $c): array
    {
        return ['id' => $c->id, 'numero' => $c->numero, 'cliente' => $c->cliente?->razon_social, 'vehiculo' => $c->vehiculo ? "{$c->vehiculo->placa} · {$c->vehiculo->marca} {$c->vehiculo->modelo}" : null, 'servicio' => $c->servicio?->nombre, 'mecanicoId' => $c->mecanico_id, 'mecanico' => $c->mecanico ? "{$c->mecanico->nombres} {$c->mecanico->apellidos}" : null, 'motivo' => $c->motivo, 'inicio' => $c->inicio->toIso8601String(), 'fin' => $c->fin->toIso8601String(), 'estado' => $c->estado, 'atendidaEn' => $c->atendida_en?->toIso8601String(), 'ordenId' => $c->orden?->id, 'ordenNumero' => $c->orden?->numero];
    }

    private function resolverServicioIa(array $respuesta, ?string $especialidadId): ?ServicioEloquentModel
    {
        $sugeridos = collect($respuesta['servicios_sugeridos'] ?? [])->filter(fn ($nombre) => is_string($nombre) && trim($nombre) !== '');
        if ($sugeridos->isEmpty()) return null;

        $servicios = ServicioEloquentModel::where('estado', 'activo')->when($especialidadId, fn ($q) => $q->where('especialidad_id', $especialidadId))->get();
        foreach ($sugeridos as $sugerido) {
            $objetivo = Str::lower(Str::ascii(trim($sugerido)));
            $coincidencia = $servicios->first(function ($servicio) use ($objetivo) {
                $nombre = Str::lower(Str::ascii($servicio->nombre));
                return $nombre === $objetivo || str_contains($objetivo, $nombre) || str_contains($nombre, $objetivo);
            });
            if ($coincidencia) return $coincidencia;
        }

        return null;
    }

    private function resumenReporteIa(array $entrada): string
    {
        $lineas = [trim((string) ($entrada['sintoma_principal'] ?? ''))];
        $campos = [
            'Momento' => $entrada['momento_ocurre'] ?? null,
            'Frecuencia' => isset($entrada['frecuencia']) ? str_replace('_', ' ', $entrada['frecuencia']) : null,
            'Desde cuándo' => $entrada['tiempo_desde_inicio'] ?? null,
            'Intensidad' => $entrada['intensidad'] ?? null,
            'Condiciones' => ! empty($entrada['condiciones']) ? implode(', ', $entrada['condiciones']) : null,
            'Señales adicionales' => $entrada['senales'] ?? null,
            'Testigos del tablero' => $entrada['luces_tablero'] ?? null,
            'Potencia o arranque' => $entrada['perdida_potencia_arranque'] ?? null,
            'Códigos OBD' => $entrada['codigos_obd'] ?? null,
            'Pruebas realizadas por el cliente' => $entrada['pruebas_realizadas'] ?? null,
            'Reparaciones recientes' => $entrada['reparaciones_recientes'] ?? null,
            'Observaciones' => $entrada['observaciones'] ?? null,
        ];
        foreach ($campos as $etiqueta => $valor) if ($valor !== null && trim((string) $valor) !== '') $lineas[] = "{$etiqueta}: {$valor}";

        return implode("\n", array_filter($lineas));
    }
}
