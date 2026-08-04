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
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteWebController extends Controller
{
    public function index(Request $request, RegistrarAuditoria $auditoria): Response
    {
        $filtros = $this->filtros($request);
        $puedeVerIngresos = $request->user()->can('reportes.financieros');
        $datos = $this->generar($filtros, $puedeVerIngresos);

        if ($puedeVerIngresos) {
            $auditoria->registrar('reporte.financiero.consultado', 'reporte', null, ['filtros' => $filtros], $request);
        }

        return Inertia::render('Reporte/index', [
            ...$datos,
            'filtros' => $filtros,
            'vista' => $request->route('vista') ?? 'filtros',
            'puedeVerIngresos' => $puedeVerIngresos,
            'puedeExportar' => $request->user()->can('reportes.exportar'),
            'catalogos' => $this->catalogos(),
        ]);
    }

    public function exportar(Request $request, RegistrarAuditoria $auditoria): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $tipo = $request->validate([
            'tipo' => ['required', Rule::in(['ordenes_pendientes', 'ordenes_finalizadas', 'ingresos', 'servicios', 'repuestos', 'vehiculos_cliente'])],
        ])['tipo'];
        $puedeVerIngresos = $request->user()->can('reportes.financieros');
        abort_if($tipo === 'ingresos' && ! $puedeVerIngresos, 403);
        if (in_array($tipo, ['ordenes_pendientes', 'ordenes_finalizadas'], true)) {
            return $this->exportarOrdenes($tipo, $filtros, $request, $auditoria);
        }
        $datos = $this->generar($filtros, $puedeVerIngresos, true, $tipo);
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
            'estado' => ['nullable', Rule::in(['pendiente', 'en_diagnostico', 'en_reparacion', 'finalizada', 'entregada', 'cancelada'])],
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

    private function generar(array $filtros, bool $incluirIngresos, bool $sinLimites = false, ?string $soloSinLimite = null): array
    {
        $desde = CarbonImmutable::parse($filtros['desde'])->startOfDay();
        $hasta = CarbonImmutable::parse($filtros['hasta'])->endOfDay();
        $ordenBase = fn () => $this->aplicarFiltrosOrden(
            DB::table('ordenes_trabajo as o')
                ->join('clientes as c', 'c.id', '=', 'o.cliente_id')
                ->join('vehiculos as v', 'v.id', '=', 'o.vehiculo_id')
                ->whereBetween('o.recibida_en', [$desde, $hasta]),
            $filtros
        );

        $totalOrdenes = $ordenBase()->count();
        $ordenesPorEstado = $ordenBase()->groupBy('o.estado')->orderBy('o.estado')->get(['o.estado', DB::raw('COUNT(*) as total')]);

        $consultaPendientes = $ordenBase()->whereIn('o.estado', ['pendiente', 'en_diagnostico', 'en_reparacion']);
        $totalPendientes = (clone $consultaPendientes)->count();
        $consultaPendientes->orderBy('o.recibida_en');
        if (! $sinLimites || $soloSinLimite !== 'ordenes_pendientes') {
            $consultaPendientes->limit(50);
        }
        $pendientes = $consultaPendientes->get(['o.id', 'o.numero', 'o.estado', 'o.recibida_en', 'c.razon_social as cliente', 'v.placa']);
        $consultaFinalizadas = $ordenBase()->whereIn('o.estado', ['finalizada', 'entregada']);
        $totalFinalizadas = (clone $consultaFinalizadas)->count();
        $consultaFinalizadas->orderByDesc('o.finalizada_en');
        if (! $sinLimites || $soloSinLimite !== 'ordenes_finalizadas') {
            $consultaFinalizadas->limit(50);
        }
        $finalizadas = $consultaFinalizadas->get(['o.id', 'o.numero', 'o.estado', 'o.finalizada_en', 'o.entregada_en', 'c.razon_social as cliente', 'v.placa']);

        $servicios = DB::table('orden_servicios as os')
            ->join('ordenes_trabajo as o', 'o.id', '=', 'os.orden_id')
            ->whereBetween('o.recibida_en', [$desde, $hasta])
            ->where('os.estado', '<>', 'cancelado')
            ->when($filtros['servicio'], fn (Builder $q, string $id) => $q->where('os.servicio_id', $id));
        $this->aplicarFiltrosRelacionados($servicios, $filtros, 'o');
        $servicios->groupBy('os.servicio_id', 'os.nombre_servicio')->orderByRaw('COUNT(*) DESC');
        if (! $sinLimites || $soloSinLimite !== 'servicios') {
            $servicios->limit(20);
        }
        $servicios = $servicios->get(['os.nombre_servicio as nombre', DB::raw('COUNT(*) as solicitudes'), DB::raw("SUM(CASE WHEN os.estado = 'completado' THEN 1 ELSE 0 END) as completados"), DB::raw('SUM(os.precio_acordado) as valor')]);

        $repuestos = DB::table('orden_repuestos as uso')
            ->join('ordenes_trabajo as o', 'o.id', '=', 'uso.orden_id')
            ->join('repuestos as r', 'r.id', '=', 'uso.repuesto_id')
            ->whereBetween('o.recibida_en', [$desde, $hasta])
            ->whereNull('uso.revertido_en');
        $this->aplicarFiltrosRelacionados($repuestos, $filtros, 'o');
        $repuestos->groupBy('uso.repuesto_id', 'r.codigo', 'r.nombre', 'r.unidad')->orderByRaw('SUM(uso.cantidad) DESC');
        if (! $sinLimites || $soloSinLimite !== 'repuestos') {
            $repuestos->limit(20);
        }
        $repuestos = $repuestos->get(['r.codigo', 'r.nombre', 'r.unidad', DB::raw('SUM(uso.cantidad) as cantidad'), DB::raw('COUNT(DISTINCT uso.orden_id) as ordenes'), DB::raw('SUM(uso.cantidad * uso.precio_unitario) as valor')]);

        $consultaVehiculos = $ordenBase()->whereIn('o.estado', ['finalizada', 'entregada'])
            ->groupBy('c.id', 'c.razon_social')->orderByRaw('COUNT(*) DESC');
        if (! $sinLimites || $soloSinLimite !== 'vehiculos_cliente') {
            $consultaVehiculos->limit(30);
        }
        $vehiculosCliente = $consultaVehiculos->get(['c.razon_social as cliente', DB::raw('COUNT(DISTINCT o.vehiculo_id) as vehiculos'), DB::raw('COUNT(*) as visitas')]);

        $ingresos = collect();
        $totalIngresos = null;
        if ($incluirIngresos) {
            $consultaIngresos = DB::table('pagos as p')
                ->join('ordenes_trabajo as o', 'o.id', '=', 'p.orden_id')
                ->where('p.estado', 'registrado')
                ->whereBetween('p.pagado_en', [$desde, $hasta]);
            $this->aplicarFiltrosRelacionados($consultaIngresos, $filtros, 'o');
            $totalIngresos = (clone $consultaIngresos)->sum('p.monto');
            $ingresos = $consultaIngresos->groupByRaw('DATE(p.pagado_en)')
                ->orderByRaw('DATE(p.pagado_en)')
                ->get([DB::raw('DATE(p.pagado_en) as fecha'), DB::raw('SUM(p.monto) as total'), DB::raw('COUNT(*) as pagos')]);
        }

        $inventarioActivo = DB::table('repuestos')->where('estado', 'activo');
        $stockOk = (clone $inventarioActivo)->whereColumn('stock_actual', '>', 'stock_minimo')->count();
        $stockBajoTotal = (clone $inventarioActivo)->where('stock_actual', '>', 0)->whereColumn('stock_actual', '<=', 'stock_minimo')->count();
        $agotados = (clone $inventarioActivo)->where('stock_actual', '<=', 0)->count();
        $stockBajo = DB::table('repuestos as r')->join('categorias_repuesto as cr', 'cr.id', '=', 'r.categoria_id')
            ->where('r.estado', 'activo')->whereColumn('r.stock_actual', '<=', 'r.stock_minimo')->orderBy('r.stock_actual')->limit(20)
            ->get(['r.id', 'r.codigo', 'r.nombre', 'r.unidad', 'r.stock_actual', 'r.stock_minimo', 'cr.nombre as categoria']);
        $iaPorEstado = DB::table('consultas_ia')->whereBetween('created_at', [$desde, $hasta])->groupBy('estado')->orderBy('estado')->get(['estado', DB::raw('COUNT(*) as total')]);

        return [
            'resumen' => [
                'totalOrdenes' => $totalOrdenes,
                'pendientes' => $totalPendientes,
                'finalizadas' => $totalFinalizadas,
                'ingresos' => $totalIngresos === null ? null : number_format((float) $totalIngresos, 2, '.', ''),
                'servicios' => $servicios->sum('solicitudes'),
                'repuestos' => number_format((float) $repuestos->sum('cantidad'), 3, '.', ''),
            ],
            'ordenesPendientes' => $pendientes,
            'ordenesFinalizadas' => $finalizadas,
            'ingresos' => $ingresos,
            'serviciosSolicitados' => $servicios,
            'repuestosUtilizados' => $repuestos,
            'vehiculosPorCliente' => $vehiculosCliente,
            'ordenesPorEstado' => $ordenesPorEstado,
            'iaPorEstado' => $iaPorEstado,
            'inventario' => ['activos' => (clone $inventarioActivo)->count(), 'ok' => $stockOk, 'bajos' => $stockBajoTotal, 'agotados' => $agotados, 'stockBajo' => $stockBajo],
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

    private function catalogos(): array
    {
        return [
            'clientes' => DB::table('clientes')->where('estado', 'activo')->orderBy('razon_social')->get(['id', 'razon_social as nombre']),
            'vehiculos' => DB::table('vehiculos')->where('estado', '<>', 'archivado')->orderBy('placa')->get(['id', 'cliente_id', 'placa as nombre']),
            'mecanicos' => DB::table('mecanicos')->where('estado', 'activo')->orderBy('nombres')->get(['id', DB::raw("CONCAT(nombres, ' ', apellidos) as nombre")]),
            'servicios' => DB::table('servicios_taller')->where('estado', 'activo')->orderBy('nombre')->get(['id', 'nombre']),
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
                ->whereBetween('o.recibida_en', [$desde, $hasta]),
            $filtros
        );

        if ($tipo === 'ordenes_pendientes') {
            $query->whereIn('o.estado', ['pendiente', 'en_diagnostico', 'en_reparacion'])->orderBy('o.recibida_en');
            $encabezados = ['Número', 'Estado', 'Ingreso', 'Cliente', 'Placa'];
            $columnas = ['o.numero', 'o.estado', 'o.recibida_en', 'c.razon_social as cliente', 'v.placa'];
        } else {
            $query->whereIn('o.estado', ['finalizada', 'entregada'])->orderByDesc('o.finalizada_en');
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
            'ordenes_finalizadas' => [['Número', 'Estado', 'Finalización', 'Entrega', 'Cliente', 'Placa'], $datos['ordenesFinalizadas']],
            'ingresos' => [['Fecha', 'Total', 'Pagos'], $datos['ingresos']],
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
