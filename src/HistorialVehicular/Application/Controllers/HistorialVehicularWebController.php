<?php

namespace Src\HistorialVehicular\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\HistorialVehicular\Infrastructure\Models\HistorialVehiculoAccionEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class HistorialVehicularWebController extends Controller
{
    private const ACCIONES = [
        'vehiculo.creado' => 'Creación de vehículo',
        'vehiculo.actualizado' => 'Edición de información',
        'vehiculo.propietario_cambiado' => 'Cambio de propietario',
        'vehiculo.estado_cambiado' => 'Cambio de estado',
        'orden.mecanicos_asignados' => 'Asignación de mecánico',
        'orden.creada' => 'Creación de orden',
        'servicio.finalizado' => 'Finalización de servicio',
        'diagnostico.registrado' => 'Diagnóstico técnico',
        'vehiculo.eliminado' => 'Eliminación de vehículo',
    ];

    public function index(Request $request): Response
    {
        $modoCliente = $request->route('modo') === 'cliente';
        if ($modoCliente) {
            abort_unless($request->user()->hasRole('Cliente'), 403);
        }
        $buscar = trim((string) $request->input('buscar'));
        $usuario = $request->user();
        $vehiculos = VehiculoEloquentModel::query()
            ->with('cliente:id,razon_social')
            ->visiblePara($usuario)
            ->when($usuario->hasRole('Mecánico'), fn (Builder $query) => $query->whereHas(
                'ordenes', fn (Builder $orden) => $orden->visiblePara($usuario)
            ))
            ->when($buscar, fn (Builder $query) => $query->where(function (Builder $sub) use ($buscar) {
                $normalizada = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($buscar));
                $sub->where('placa_normalizada', 'ilike', "%{$normalizada}%")
                    ->orWhere('marca', 'ilike', "%{$buscar}%")
                    ->orWhere('modelo', 'ilike', "%{$buscar}%")
                    ->orWhereHas('cliente', fn (Builder $cliente) => $cliente->where('razon_social', 'ilike', "%{$buscar}%"));
            }))
            ->withCount(['ordenes as visitas' => fn (Builder $orden) => $orden->visiblePara($usuario)])
            ->orderBy('placa')
            ->paginate(15)
            ->withQueryString();

        $vehiculos->through(fn (VehiculoEloquentModel $vehiculo) => [
            'id' => $vehiculo->id,
            'placa' => $vehiculo->placa,
            'marca' => $vehiculo->marca,
            'modelo' => $vehiculo->modelo,
            'anio' => $vehiculo->anio,
            'kilometraje' => $vehiculo->kilometraje,
            'estado' => $vehiculo->estado,
            'propietario' => $vehiculo->cliente?->razon_social,
            'visitas' => $vehiculo->visitas,
        ]);

        return Inertia::render('HistorialVehicular/index', [
            'vehiculos' => $vehiculos,
            'buscar' => $buscar,
            'modoCliente' => $modoCliente,
        ]);
    }

    public function show(Request $request, VehiculoEloquentModel $vehiculo): Response
    {
        $usuario = $request->user();
        $modoCliente = $request->route('modo') === 'cliente';
        if ($modoCliente) {
            abort_unless($usuario->hasRole('Cliente'), 403);
        }
        abort_unless(VehiculoEloquentModel::whereKey($vehiculo->id)->visiblePara($usuario)->exists(), 403);
        if ($usuario->hasRole('Mecánico')) {
            abort_unless(OrdenTrabajoEloquentModel::where('vehiculo_id', $vehiculo->id)->visiblePara($usuario)->exists(), 403);
        }

        $filtros = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(['pendiente', 'en_diagnostico', 'en_reparacion', 'finalizada', 'entregada', 'cancelada'])],
            'servicio' => ['nullable', 'uuid'],
            'buscar' => ['nullable', 'string', 'max:120'],
            'orden' => ['nullable', Rule::in(['reciente', 'antiguo'])],
        ]);
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        $ordenes = OrdenTrabajoEloquentModel::query()
            ->where('vehiculo_id', $vehiculo->id)
            ->visiblePara($usuario)
            ->with([
                'cliente:id,razon_social',
                'servicios' => fn ($query) => $query->orderBy('created_at'),
                'asignaciones.mecanico:id,nombres,apellidos',
                'diagnosticos' => fn ($query) => $query->orderByDesc('version'),
            ])
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $desde) => $query->whereDate('recibida_en', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $hasta) => $query->whereDate('recibida_en', '<=', $hasta))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('estado', $estado))
            ->when($filtros['servicio'] ?? null, fn (Builder $query, string $servicio) => $query->whereHas('servicios', fn (Builder $linea) => $linea->where('servicio_id', $servicio)))
            ->when($buscar, fn (Builder $query) => $query->where(function (Builder $sub) use ($buscar) {
                $sub->where('numero', 'ilike', "%{$buscar}%")
                    ->orWhere('falla_reportada', 'ilike', "%{$buscar}%")
                    ->orWhereHas('servicios', fn (Builder $servicio) => $servicio->where('nombre_servicio', 'ilike', "%{$buscar}%")->orWhere('observaciones', 'ilike', "%{$buscar}%"))
                    ->orWhereHas('diagnosticos', fn (Builder $diagnostico) => $diagnostico->where('diagnostico', 'ilike', "%{$buscar}%"));
            }))
            ->orderBy('recibida_en', ($filtros['orden'] ?? 'reciente') === 'antiguo' ? 'asc' : 'desc')
            ->paginate(10)
            ->withQueryString();

        $ids = $ordenes->getCollection()->pluck('id');
        $puedeVerFinanzas = $usuario->can('historial.finanzas.ver');
        $camposRepuestos = ['uso.orden_id', 'repuesto.codigo', 'repuesto.nombre', 'uso.cantidad', 'uso.revertido_en'];
        $camposRepuestos[] = $puedeVerFinanzas ? 'uso.precio_unitario' : DB::raw('NULL as precio_unitario');
        $repuestos = DB::table('orden_repuestos as uso')
            ->join('repuestos as repuesto', 'repuesto.id', '=', 'uso.repuesto_id')
            ->whereIn('uso.orden_id', $ids)
            ->orderBy('uso.created_at')
            ->get($camposRepuestos)
            ->groupBy('orden_id');

        $pagos = $puedeVerFinanzas
            ? DB::table('pagos')->whereIn('orden_id', $ids)->orderBy('pagado_en')->get()->groupBy('orden_id')
            : collect();
        $facturas = $puedeVerFinanzas
            ? DB::table('facturas_orden')->whereIn('orden_id', $ids)->orderByDesc('emitida_en')->get()->groupBy('orden_id')
            : collect();

        $ordenes->through(function (OrdenTrabajoEloquentModel $orden) use ($repuestos, $pagos, $facturas, $puedeVerFinanzas) {
            $usos = $repuestos->get($orden->id, collect());
            $pagosOrden = $pagos->get($orden->id, collect());
            $facturasOrden = $facturas->get($orden->id, collect());
            $facturaVigente = $facturasOrden->firstWhere('estado', 'emitida');
            $serviciosTotal = $orden->servicios->where('estado', '!=', 'cancelado')->sum(fn ($servicio) => (float) $servicio->precio_acordado);
            $repuestosTotal = $usos->whereNull('revertido_en')->sum(fn ($uso) => (float) $uso->cantidad * (float) $uso->precio_unitario);
            $total = $facturaVigente ? (float) $facturaVigente->total : $serviciosTotal + $repuestosTotal;
            $pagado = $pagosOrden->where('estado', 'registrado')->sum(fn ($pago) => (float) $pago->monto);
            $saldo = max(0, $total - $pagado);
            $estadoPago = $pagado <= 0 ? 'pendiente' : ($saldo <= 0 ? 'pagado' : 'parcial');
            if ($facturaVigente && $saldo > 0 && $facturaVigente->vence_en && now()->startOfDay()->gt(\Carbon\CarbonImmutable::parse($facturaVigente->vence_en))) {
                $estadoPago = 'vencido';
            }

            return [
                'id' => $orden->id,
                'numero' => $orden->numero,
                'estado' => $orden->estado,
                'fallaReportada' => $orden->falla_reportada,
                'kilometraje' => $orden->kilometraje,
                'recibidaEn' => $orden->recibida_en,
                'finalizadaEn' => $orden->finalizada_en,
                'entregadaEn' => $orden->entregada_en,
                'motivoCancelacion' => $orden->motivo_cancelacion,
                'mecanicos' => $orden->asignaciones->map(fn ($asignacion) => [
                    'nombre' => trim("{$asignacion->mecanico?->nombres} {$asignacion->mecanico?->apellidos}"),
                    'activo' => $asignacion->activo,
                    'asignadoEn' => $asignacion->asignado_en,
                    'retiradoEn' => $asignacion->retirado_en,
                ])->values(),
                'diagnosticos' => $orden->diagnosticos->map(fn ($diagnostico) => [
                    'version' => $diagnostico->version,
                    'diagnostico' => $diagnostico->diagnostico,
                    'pruebas' => $diagnostico->pruebas_realizadas,
                    'recomendaciones' => $diagnostico->recomendaciones,
                    'vigente' => $diagnostico->vigente,
                    'registradoEn' => $diagnostico->created_at,
                ])->values(),
                'servicios' => $orden->servicios->map(fn ($servicio) => [
                    'nombre' => $servicio->nombre_servicio,
                    'precio' => $puedeVerFinanzas ? $servicio->precio_acordado : null,
                    'estado' => $servicio->estado,
                    'observaciones' => $servicio->observaciones,
                ])->values(),
                'repuestos' => $usos->map(fn ($uso) => [
                    'codigo' => $uso->codigo,
                    'nombre' => $uso->nombre,
                    'cantidad' => $uso->cantidad,
                    'precio' => $puedeVerFinanzas ? $uso->precio_unitario : null,
                    'revertido' => $uso->revertido_en !== null,
                ])->values(),
                'finanzas' => $puedeVerFinanzas ? [
                    'servicios' => number_format($serviciosTotal, 2, '.', ''),
                    'repuestos' => number_format($repuestosTotal, 2, '.', ''),
                    'total' => number_format($total, 2, '.', ''),
                    'pagado' => number_format($pagado, 2, '.', ''),
                    'saldo' => number_format($saldo, 2, '.', ''),
                    'estado' => $estadoPago,
                    'factura' => $facturaVigente ? [
                        'id' => $facturaVigente->id,
                        'numero' => $facturaVigente->numero,
                        'estado' => $facturaVigente->estado,
                    ] : null,
                    'pagos' => $pagosOrden->map(fn ($pago) => [
                        'numero' => $pago->numero,
                        'comprobante' => $pago->comprobante_numero,
                        'monto' => $pago->monto,
                        'estado' => $pago->estado,
                        'pagadoEn' => $pago->pagado_en,
                    ])->values(),
                ] : null,
            ];
        });

        $vehiculo->load('cliente:id,razon_social');

        return Inertia::render('HistorialVehicular/show', [
            'vehiculo' => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'anio' => $vehiculo->anio,
                'color' => $vehiculo->color,
                'kilometraje' => $vehiculo->kilometraje,
                'combustible' => $vehiculo->combustible,
                'estado' => $vehiculo->estado,
                'observaciones' => $vehiculo->observaciones,
                'propietario' => $vehiculo->cliente?->razon_social,
            ],
            'ordenes' => $ordenes,
            'filtros' => $filtros,
            'servicios' => ServicioEloquentModel::where('estado', 'activo')->orderBy('nombre')->get(['id', 'nombre']),
            'puedeVerFinanzas' => $puedeVerFinanzas,
            'modoCliente' => $modoCliente,
        ]);
    }

    public function bitacora(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:120'],
            'vehiculo' => ['nullable', 'uuid', 'exists:vehiculos,id'],
            'usuario' => ['nullable', 'uuid', 'exists:users,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'accion' => ['nullable', Rule::in(array_keys(self::ACCIONES))],
        ]);
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $registros = HistorialVehiculoAccionEloquentModel::query()
            ->with(['vehiculo:id,placa,marca,modelo', 'usuario:id,name,email'])
            ->when($buscar, fn (Builder $query) => $query->where(function (Builder $sub) use ($buscar) {
                $sub->where('descripcion', 'ilike', "%{$buscar}%")
                    ->orWhereHas('vehiculo', fn (Builder $vehiculo) => $vehiculo->where('placa', 'ilike', "%{$buscar}%"))
                    ->orWhereHas('usuario', fn (Builder $usuario) => $usuario->where('name', 'ilike', "%{$buscar}%"));
            }))
            ->when($filtros['vehiculo'] ?? null, fn (Builder $query, string $id) => $query->where('vehiculo_id', $id))
            ->when($filtros['usuario'] ?? null, fn (Builder $query, string $id) => $query->where('usuario_id', $id))
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $fecha) => $query->whereDate('created_at', '>=', $fecha))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $fecha) => $query->whereDate('created_at', '<=', $fecha))
            ->when($filtros['accion'] ?? null, fn (Builder $query, string $accion) => $query->where('accion', $accion))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $registros->through(fn (HistorialVehiculoAccionEloquentModel $registro) => [
            'id' => $registro->id,
            'fecha' => $registro->created_at,
            'accion' => $registro->accion,
            'accionLabel' => self::ACCIONES[$registro->accion] ?? $registro->accion,
            'descripcion' => $registro->descripcion,
            'cambios' => $registro->cambios,
            'ip' => $registro->ip,
            'rol' => $registro->rol ?: 'Sin rol',
            'usuario' => $registro->usuario ? ['nombre' => $registro->usuario->name, 'email' => $registro->usuario->email] : null,
            'vehiculo' => ['id' => $registro->vehiculo->id, 'placa' => $registro->vehiculo->placa, 'descripcion' => trim("{$registro->vehiculo->marca} {$registro->vehiculo->modelo}")],
        ]);

        return Inertia::render('HistorialVehicular/bitacora', [
            'registros' => $registros,
            'vehiculos' => VehiculoEloquentModel::orderBy('placa')->get(['id', 'placa', 'marca', 'modelo'])->map(fn ($vehiculo) => ['value' => $vehiculo->id, 'label' => "{$vehiculo->placa} · {$vehiculo->marca} {$vehiculo->modelo}"]),
            'usuarios' => UserEloquentModel::orderBy('name')->get(['id', 'name', 'email'])->map(fn ($usuario) => ['value' => $usuario->id, 'label' => "{$usuario->name} · {$usuario->email}"]),
            'acciones' => collect(self::ACCIONES)->map(fn ($label, $value) => compact('label', 'value'))->values(),
            'filtros' => [...$filtros, 'buscar' => $buscar],
        ]);
    }
}
