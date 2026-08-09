<?php

namespace Src\Reporte\Application\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteWebController extends Controller
{
    public function index(Request $request, RegistrarAuditoria $auditoria): Response
    {
        $filtros = $this->filtros($request);
        $puedeVerIngresos = $request->user()->can('reportes.financieros');
        $capacidades = [
            'ingresos' => $puedeVerIngresos,
            'valores' => $puedeVerIngresos,
            'ia' => $request->user()->can('ia.revisar'),
            'inventario' => $request->user()->can('inventario.ver'),
            'clientes' => $request->user()->can('clientes.ver'),
        ];
        $datos = $this->generar($filtros, $request->user(), $capacidades);

        if ($puedeVerIngresos) {
            $auditoria->registrar('reporte.financiero.consultado', 'reporte', null, ['filtros' => $filtros], $request);
        }

        return Inertia::render('Reporte/index', [
            ...$datos,
            'filtros' => $filtros,
            'vista' => $request->route('vista') ?? 'filtros',
            'puedeVerIngresos' => $puedeVerIngresos,
            'puedeExportar' => $request->user()->can('reportes.exportar'),
            'capacidades' => $capacidades,
            'catalogos' => $this->catalogos($request->user()),
        ]);
    }

    public function exportar(Request $request, RegistrarAuditoria $auditoria): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $tipo = $request->validate([
            'tipo' => ['required', Rule::in(['ordenes_pendientes', 'ordenes_en_reparacion', 'ordenes_finalizadas', 'diagnosticos_ia', 'ingresos', 'servicios', 'repuestos', 'vehiculos_cliente'])],
        ])['tipo'];
        $puedeVerIngresos = $request->user()->can('reportes.financieros');
        abort_if($tipo === 'ingresos' && ! $puedeVerIngresos, 403);
        abort_if($tipo === 'diagnosticos_ia' && ! $request->user()->can('ia.revisar'), 403);
        abort_if($tipo === 'vehiculos_cliente' && ! $request->user()->can('clientes.ver'), 403);
        if (in_array($tipo, ['ordenes_pendientes', 'ordenes_en_reparacion', 'ordenes_finalizadas'], true)) {
            return $this->exportarOrdenes($tipo, $filtros, $request, $auditoria);
        }
        $capacidades = ['ingresos' => $puedeVerIngresos, 'valores' => $puedeVerIngresos, 'ia' => $request->user()->can('ia.revisar'), 'inventario' => false, 'clientes' => $request->user()->can('clientes.ver')];
        $datos = $this->generar($filtros, $request->user(), $capacidades, true, $tipo);
        [$encabezados, $filas] = $this->filasExportacion($tipo, $datos);

        $auditoria->registrar('reporte.exportado', 'reporte', null, [
            'tipo' => $tipo,
            'formato' => 'csv',
            'filas' => $filas->count(),
            'filtros' => $filtros,
        ], $request);

        $nombre = "reporte-{$tipo}-".now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($encabezados, $filas) {
            $salida = fopen('php://output', 'wb');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $encabezados, ';');
            foreach ($filas as $fila) {
                fputcsv($salida, array_map([$this, 'celdaSegura'], array_values((array) $fila)), ';');
            }
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(['pendiente','asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba','finalizada','lista_entrega','entregada','cancelada'])],
            'mecanico' => ['nullable', 'uuid'],
            'cliente' => ['nullable', 'uuid'],
            'vehiculo' => ['nullable', 'uuid'],
            'servicio' => ['nullable', 'uuid'],
        ]);

        return [
            'desde' => $validados['desde'] ?? now()->startOfMonth()->toDateString(),
            'hasta' => $validados['hasta'] ?? now()->toDateString(),
            'estado' => $validados['estado'] ?? null,
            'mecanico' => $validados['mecanico'] ?? null,
            'cliente' => $validados['cliente'] ?? null,
            'vehiculo' => $validados['vehiculo'] ?? null,
            'servicio' => $validados['servicio'] ?? null,
        ];
    }

    private function generar(array $filtros, UserEloquentModel $usuario, array $capacidades, bool $sinLimites = false, ?string $soloSinLimite = null): array
    {
        $desde = CarbonImmutable::parse($filtros['desde'])->startOfDay();
        $hasta = CarbonImmutable::parse($filtros['hasta'])->endOfDay();
        $ordenesVisibles = OrdenTrabajoEloquentModel::query()->visiblePara($usuario)->select('ordenes_trabajo.id');
        $ordenBase = fn () => $this->aplicarFiltrosOrden(
            DB::table('ordenes_trabajo as o')
                ->join('clientes as c', 'c.id', '=', 'o.cliente_id')
                ->join('vehiculos as v', 'v.id', '=', 'o.vehiculo_id')
                ->whereIn('o.id', clone $ordenesVisibles)
                ->whereBetween('o.recibida_en', [$desde, $hasta]),
            $filtros
        );

        $totalOrdenes = $ordenBase()->count();
        $ordenesPorEstado = $ordenBase()->groupBy('o.estado')->orderBy('o.estado')->get(['o.estado', DB::raw('COUNT(*) as total')]);

        $consultaPendientes = $ordenBase()->whereIn('o.estado', ['pendiente','asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba']);
        $totalPendientes = (clone $consultaPendientes)->count();
        $consultaPendientes->orderBy('o.recibida_en');
        if (! $sinLimites || $soloSinLimite !== 'ordenes_pendientes') {
            $consultaPendientes->limit(50);
        }
        $pendientes = $consultaPendientes->get(['o.id', 'o.numero', 'o.estado', 'o.recibida_en', 'c.razon_social as cliente', 'v.placa']);
        $consultaEnReparacion = $ordenBase()->where('o.estado', 'en_reparacion');
        $totalEnReparacion = (clone $consultaEnReparacion)->count();
        $consultaEnReparacion->orderBy('o.recibida_en');
        if (! $sinLimites || $soloSinLimite !== 'ordenes_en_reparacion') {
            $consultaEnReparacion->limit(50);
        }
        $enReparacion = $consultaEnReparacion->get(['o.id', 'o.numero', 'o.estado', 'o.recibida_en', 'c.razon_social as cliente', 'v.placa']);
        $consultaFinalizadas = $ordenBase()->whereIn('o.estado', ['finalizada', 'lista_entrega', 'entregada']);
        $totalFinalizadas = (clone $consultaFinalizadas)->count();
        $consultaFinalizadas->orderByDesc('o.finalizada_en');
        if (! $sinLimites || $soloSinLimite !== 'ordenes_finalizadas') {
            $consultaFinalizadas->limit(50);
        }
        $finalizadas = $consultaFinalizadas->get(['o.id', 'o.numero', 'o.estado', 'o.finalizada_en', 'o.entregada_en', 'c.razon_social as cliente', 'v.placa']);

        $servicios = DB::table('orden_servicios as os')
            ->join('ordenes_trabajo as o', 'o.id', '=', 'os.orden_id')
            ->whereIn('o.id', clone $ordenesVisibles)
            ->whereBetween('o.recibida_en', [$desde, $hasta])
            ->where('os.estado', '<>', 'cancelado')
            ->when($filtros['servicio'], fn (Builder $q, string $id) => $q->where('os.servicio_id', $id));
        $this->aplicarFiltrosRelacionados($servicios, $filtros, 'o');
        $servicios->groupBy('os.servicio_id', 'os.nombre_servicio')->orderByRaw('COUNT(*) DESC');
        if (! $sinLimites || $soloSinLimite !== 'servicios') {
            $servicios->limit(20);
        }
        $columnasServicios = ['os.nombre_servicio as nombre', DB::raw('COUNT(*) as solicitudes'), DB::raw("SUM(CASE WHEN os.estado = 'completado' THEN 1 ELSE 0 END) as completados")];
        if ($capacidades['valores']) $columnasServicios[] = DB::raw('SUM(os.precio_acordado) as valor');
        $servicios = $servicios->get($columnasServicios);

        $repuestos = DB::table('orden_repuestos as uso')
            ->join('ordenes_trabajo as o', 'o.id', '=', 'uso.orden_id')
            ->join('repuestos as r', 'r.id', '=', 'uso.repuesto_id')
            ->whereIn('o.id', clone $ordenesVisibles)
            ->whereBetween('uso.created_at', [$desde, $hasta])
            ->where('uso.fuente_suministro', 'inventario')
            ->whereNull('uso.revertido_en');
        $this->aplicarFiltrosRelacionados($repuestos, $filtros, 'o');
        $repuestos->groupBy('uso.repuesto_id', 'uso.codigo_snapshot', 'uso.nombre_snapshot', 'uso.unidad_snapshot', 'r.codigo', 'r.nombre', 'r.unidad')->orderByRaw('SUM(uso.cantidad) DESC');
        if (! $sinLimites || $soloSinLimite !== 'repuestos') {
            $repuestos->limit(20);
        }
        $columnasRepuestos = [DB::raw('COALESCE(uso.codigo_snapshot, r.codigo) as codigo'), DB::raw('COALESCE(uso.nombre_snapshot, r.nombre) as nombre'), DB::raw('COALESCE(uso.unidad_snapshot, r.unidad) as unidad'), DB::raw('SUM(uso.cantidad) as cantidad'), DB::raw('COUNT(DISTINCT uso.orden_id) as ordenes')];
        if ($capacidades['valores']) $columnasRepuestos[] = DB::raw('SUM(uso.cantidad * uso.precio_unitario) as valor');
        $repuestos = $repuestos->get($columnasRepuestos);

        $vehiculosCliente = collect();
        if ($capacidades['clientes']) {
            $consultaVehiculos = $ordenBase()->whereIn('o.estado', ['finalizada', 'lista_entrega', 'entregada'])
                ->groupBy('c.id', 'c.razon_social')->orderByRaw('COUNT(*) DESC');
            if (! $sinLimites || $soloSinLimite !== 'vehiculos_cliente') {
                $consultaVehiculos->limit(30);
            }
            $vehiculosCliente = $consultaVehiculos->get(['c.razon_social as cliente', DB::raw('COUNT(DISTINCT o.vehiculo_id) as vehiculos'), DB::raw('COUNT(*) as visitas')]);
        }

        $ingresos = collect();
        $totalIngresos = null;
        if ($capacidades['ingresos']) {
            $consultaIngresos = DB::table('pago_movimientos as movimiento')
                ->join('ordenes_trabajo as o', 'o.id', '=', 'movimiento.orden_id')
                ->whereIn('o.id', clone $ordenesVisibles)
                ->whereBetween('movimiento.ocurrido_en', [$desde, $hasta]);
            $this->aplicarFiltrosRelacionados($consultaIngresos, $filtros, 'o');
            $totalIngresos = (clone $consultaIngresos)->sum('movimiento.monto');
            $ingresos = $consultaIngresos->groupByRaw('DATE(movimiento.ocurrido_en)')
                ->orderByRaw('DATE(movimiento.ocurrido_en)')
                ->get([DB::raw('DATE(movimiento.ocurrido_en) as fecha'), DB::raw('SUM(movimiento.monto) as total'), DB::raw('COUNT(*) as pagos')]);
        }

        $inventario = ['activos' => 0, 'ok' => 0, 'bajos' => 0, 'agotados' => 0, 'stockBajo' => collect()];
        if ($capacidades['inventario']) {
            $inventarioActivo = DB::table('repuestos')->where('estado', 'activo');
            $inventario = [
                'activos' => (clone $inventarioActivo)->count(),
                'ok' => (clone $inventarioActivo)->whereColumn('stock_actual', '>', 'stock_minimo')->count(),
                'bajos' => (clone $inventarioActivo)->where('stock_actual', '>', 0)->whereColumn('stock_actual', '<=', 'stock_minimo')->count(),
                'agotados' => (clone $inventarioActivo)->where('stock_actual', '<=', 0)->count(),
                'stockBajo' => DB::table('repuestos as r')->join('categorias_repuesto as cr', 'cr.id', '=', 'r.categoria_id')
                    ->where('r.estado', 'activo')->whereColumn('r.stock_actual', '<=', 'r.stock_minimo')->orderBy('r.stock_actual')->limit(20)
                    ->get(['r.id', 'r.codigo', 'r.nombre', 'r.unidad', 'r.stock_actual', 'r.stock_minimo', 'cr.nombre as categoria']),
            ];
        }

        $iaPorEstado = collect();
        if ($capacidades['ia']) {
            $consultasVisibles = ConsultaIaEloquentModel::query()->visiblePara($usuario)->select('consultas_ia.id');
            $consultaIa = DB::table('consultas_ia as ia')->whereIn('ia.id', $consultasVisibles)->whereBetween('ia.created_at', [$desde, $hasta]);
            $this->aplicarFiltrosIa($consultaIa, $filtros);
            $iaPorEstado = $consultaIa->groupBy('ia.estado')->orderBy('ia.estado')->get(['ia.estado', DB::raw('COUNT(*) as total')]);
            $iaPorEstado->each(fn ($item) => $item->total = (int) $item->total);
        }

        return [
            'resumen' => [
                'totalOrdenes' => $totalOrdenes,
                'pendientes' => $totalPendientes,
                'enReparacion' => $totalEnReparacion,
                'finalizadas' => $totalFinalizadas,
                'totalIa' => $iaPorEstado->sum('total'),
                'ingresos' => $totalIngresos === null ? null : number_format((float) $totalIngresos, 2, '.', ''),
                'servicios' => $servicios->sum('solicitudes'),
                'repuestos' => number_format((float) $repuestos->sum('cantidad'), 3, '.', ''),
            ],
            'ordenesPendientes' => $pendientes,
            'ordenesEnReparacion' => $enReparacion,
            'ordenesFinalizadas' => $finalizadas,
            'ingresos' => $ingresos,
            'serviciosSolicitados' => $servicios,
            'repuestosUtilizados' => $repuestos,
            'vehiculosPorCliente' => $vehiculosCliente,
            'ordenesPorEstado' => $ordenesPorEstado,
            'iaPorEstado' => $iaPorEstado,
            'inventario' => $inventario,
        ];
    }

    private function aplicarFiltrosOrden(Builder $query, array $filtros): Builder
    {
        $this->aplicarFiltrosRelacionados($query, $filtros, 'o');
        return $query;
    }

    private function aplicarFiltrosRelacionados(Builder $query, array $filtros, string $alias): void
    {
        $query->when($filtros['cliente'], fn (Builder $q, string $id) => $q->where("{$alias}.cliente_id", $id))
            ->when($filtros['vehiculo'], fn (Builder $q, string $id) => $q->where("{$alias}.vehiculo_id", $id))
            ->when($filtros['estado'], fn (Builder $q, string $estado) => $q->where("{$alias}.estado", $estado))
            ->when($filtros['mecanico'], fn (Builder $q, string $id) => $q->whereExists(fn (Builder $sub) => $sub->selectRaw('1')->from('orden_mecanicos as om')->whereColumn('om.orden_id', "{$alias}.id")->where('om.mecanico_id', $id)))
            ->when($filtros['servicio'], fn (Builder $q, string $id) => $q->whereExists(fn (Builder $sub) => $sub->selectRaw('1')->from('orden_servicios as filtro_servicio')->whereColumn('filtro_servicio.orden_id', "{$alias}.id")->where('filtro_servicio.servicio_id', $id)));
    }

    private function aplicarFiltrosIa(Builder $query, array $filtros): void
    {
        $query->when($filtros['cliente'], fn (Builder $q, string $id) => $q->where('ia.cliente_id', $id))
            ->when($filtros['vehiculo'], fn (Builder $q, string $id) => $q->where('ia.vehiculo_id', $id));

        if ($filtros['estado'] || $filtros['mecanico'] || $filtros['servicio']) {
            $query->whereExists(function (Builder $orden) use ($filtros) {
                $orden->selectRaw('1')->from('ordenes_trabajo as filtro_orden')->whereColumn('filtro_orden.id', 'ia.orden_id')
                    ->when($filtros['estado'], fn (Builder $q, string $estado) => $q->where('filtro_orden.estado', $estado))
                    ->when($filtros['mecanico'], fn (Builder $q, string $id) => $q->whereExists(fn (Builder $sub) => $sub->selectRaw('1')->from('orden_mecanicos as filtro_mecanico')->whereColumn('filtro_mecanico.orden_id', 'filtro_orden.id')->where('filtro_mecanico.mecanico_id', $id)->where('filtro_mecanico.activo', true)))
                    ->when($filtros['servicio'], fn (Builder $q, string $id) => $q->whereExists(fn (Builder $sub) => $sub->selectRaw('1')->from('orden_servicios as filtro_servicio')->whereColumn('filtro_servicio.orden_id', 'filtro_orden.id')->where('filtro_servicio.servicio_id', $id)));
            });
        }
    }

    private function catalogos(UserEloquentModel $usuario): array
    {
        $ordenesVisibles = OrdenTrabajoEloquentModel::query()->visiblePara($usuario);
        return [
            'clientes' => DB::table('clientes')->where('estado', 'activo')->whereIn('id', (clone $ordenesVisibles)->select('cliente_id'))->orderBy('razon_social')->get(['id', 'razon_social as nombre']),
            'vehiculos' => DB::table('vehiculos')->where('estado', '<>', 'archivado')->whereIn('id', (clone $ordenesVisibles)->select('vehiculo_id'))->orderBy('placa')->get(['id', 'cliente_id', 'placa as nombre']),
            'mecanicos' => DB::table('mecanicos')->where('estado', 'activo')->whereIn('id', DB::table('orden_mecanicos')->where('activo', true)->whereIn('orden_id', (clone $ordenesVisibles)->select('id'))->select('mecanico_id'))->orderBy('nombres')->get(['id', DB::raw("CONCAT(nombres, ' ', apellidos) as nombre")]),
            'servicios' => DB::table('servicios_taller')->where('estado', 'activo')->whereIn('id', DB::table('orden_servicios')->whereIn('orden_id', (clone $ordenesVisibles)->select('id'))->select('servicio_id'))->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    private function exportarOrdenes(string $tipo, array $filtros, Request $request, RegistrarAuditoria $auditoria): StreamedResponse
    {
        $desde = CarbonImmutable::parse($filtros['desde'])->startOfDay();
        $hasta = CarbonImmutable::parse($filtros['hasta'])->endOfDay();
        $query = $this->aplicarFiltrosOrden(
            DB::table('ordenes_trabajo as o')
                ->join('clientes as c', 'c.id', '=', 'o.cliente_id')
                ->join('vehiculos as v', 'v.id', '=', 'o.vehiculo_id')
                ->whereIn('o.id', OrdenTrabajoEloquentModel::query()->visiblePara($request->user())->select('ordenes_trabajo.id'))
                ->whereBetween('o.recibida_en', [$desde, $hasta]),
            $filtros
        );

        if ($tipo === 'ordenes_pendientes') {
            $query->whereIn('o.estado', ['pendiente','asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba'])->orderBy('o.recibida_en');
            $encabezados = ['Número', 'Estado', 'Ingreso', 'Cliente', 'Placa'];
            $columnas = ['o.numero', 'o.estado', 'o.recibida_en', 'c.razon_social as cliente', 'v.placa'];
        } elseif ($tipo === 'ordenes_en_reparacion') {
            $query->where('o.estado', 'en_reparacion')->orderBy('o.recibida_en');
            $encabezados = ['Número', 'Estado', 'Ingreso', 'Cliente', 'Placa'];
            $columnas = ['o.numero', 'o.estado', 'o.recibida_en', 'c.razon_social as cliente', 'v.placa'];
        } else {
            $query->whereIn('o.estado', ['finalizada', 'lista_entrega', 'entregada'])->orderByDesc('o.finalizada_en');
            $encabezados = ['Número', 'Estado', 'Finalización', 'Entrega', 'Cliente', 'Placa'];
            $columnas = ['o.numero', 'o.estado', 'o.finalizada_en', 'o.entregada_en', 'c.razon_social as cliente', 'v.placa'];
        }

        $filas = (clone $query)->count();
        $auditoria->registrar('reporte.exportado', 'reporte', null, [
            'tipo' => $tipo, 'formato' => 'csv', 'filas' => $filas, 'filtros' => $filtros,
        ], $request);
        $nombre = "reporte-{$tipo}-".now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $columnas, $encabezados) {
            $salida = fopen('php://output', 'wb');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $encabezados, ';');
            foreach ($query->select($columnas)->cursor() as $fila) {
                fputcsv($salida, array_map([$this, 'celdaSegura'], array_values((array) $fila)), ';');
            }
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filasExportacion(string $tipo, array $datos): array
    {
        return match ($tipo) {
            'ordenes_pendientes' => [['Número', 'Estado', 'Ingreso', 'Cliente', 'Placa'], $datos['ordenesPendientes']],
            'ordenes_en_reparacion' => [['Número', 'Estado', 'Ingreso', 'Cliente', 'Placa'], $datos['ordenesEnReparacion']],
            'ordenes_finalizadas' => [['Número', 'Estado', 'Finalización', 'Entrega', 'Cliente', 'Placa'], $datos['ordenesFinalizadas']],
            'diagnosticos_ia' => [['Estado', 'Total'], $datos['iaPorEstado']],
            'ingresos' => [['Fecha', 'Movimiento neto', 'Movimientos'], $datos['ingresos']],
            'servicios' => [['Servicio', 'Solicitudes', 'Completados', 'Valor'], $datos['serviciosSolicitados']],
            'repuestos' => [['Código', 'Repuesto', 'Unidad', 'Cantidad', 'Órdenes', 'Valor'], $datos['repuestosUtilizados']],
            'vehiculos_cliente' => [['Cliente', 'Vehículos', 'Visitas'], $datos['vehiculosPorCliente']],
        };
    }

    private function celdaSegura(mixed $valor): string
    {
        $texto = (string) ($valor ?? '');
        return preg_match('/^[=+\-@]/', $texto) ? "'{$texto}" : $texto;
    }
}
