<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;
use Src\Cita\Application\Services\VencerCitasFinalizadas;
use Src\Inventario\Infrastructure\Models\RepuestoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class DashboardController extends Controller
{
    /**
     * Mostrar el dashboard principal
     */
    public function index(Request $request, VencerCitasFinalizadas $vencer): Response
    {
        $vencer->ejecutar();
        $usuario = $request->user();
        abort_unless($usuario->can('ordenes.ver') || $usuario->can('vehiculos.ver') || $usuario->can('citas.ver'), 403);
        $esCliente = $usuario->hasRole('Cliente');
        $ordenesVisibles = fn () => OrdenTrabajoEloquentModel::query()->visiblePara($usuario);
        $ordenesRecibidas = fn () => $ordenesVisibles()->yaRecibidas();
        $vehiculosVisibles = VehiculoEloquentModel::query()->visiblePara($usuario);
        if ($usuario->hasRole('Mecánico')) {
            $vehiculosVisibles->whereHas('ordenes', fn (Builder $orden) => $orden->visiblePara($usuario));
        }

        $ordenesActivas = $ordenesRecibidas()->whereIn('estado', ['pendiente','asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba','finalizada','lista_entrega'])->count();
        $ordenesFinalizadasHoy = $ordenesVisibles()->whereIn('estado', ['finalizada', 'lista_entrega', 'entregada'])->whereDate('finalizada_en', today())->count();
        $ordenesEnReparacion = $ordenesRecibidas()->where('estado', 'en_reparacion')->count();
        $citasHoy = $usuario->can('citas.ver')
            ? CitaEloquentModel::query()->visiblePara($usuario)->whereDate('inicio', today())->whereNot('estado', 'cancelada')->count()
            : 0;
        $ingresosHoy = $usuario->can('pagos.ver')
            ? (float) DB::table('pago_movimientos')->whereIn('orden_id', $ordenesVisibles()->select('ordenes_trabajo.id'))->whereDate('ocurrido_en', today())->sum('monto')
            : null;
        $stockBajo = $usuario->can('inventario.ver')
            ? RepuestoEloquentModel::where('estado', 'activo')->whereColumn('stock_actual', '<=', 'stock_minimo')->count()
            : null;

        $metricas = [
            ['label' => $esCliente ? 'Mis vehículos' : 'Vehículos registrados', 'value' => $vehiculosVisibles->count(), 'icon' => 'i-lucide-car-front', 'tone' => 'primary'],
            ['label' => 'Órdenes activas', 'value' => $ordenesActivas, 'icon' => 'i-lucide-gauge', 'tone' => $ordenesActivas ? 'warning' : 'success'],
            ['label' => $usuario->can('citas.ver') ? 'Citas de hoy' : 'Finalizadas hoy', 'value' => $usuario->can('citas.ver') ? $citasHoy : $ordenesFinalizadasHoy, 'icon' => $usuario->can('citas.ver') ? 'i-lucide-calendar-clock' : 'i-lucide-circle-check-big', 'tone' => 'info'],
            $ingresosHoy !== null
                ? ['label' => 'Ingresos de hoy', 'value' => number_format($ingresosHoy, 0, ',', '.'), 'prefix' => '$', 'icon' => 'i-lucide-wallet-cards', 'tone' => 'success']
                : ['label' => 'Órdenes en reparación', 'value' => $ordenesEnReparacion, 'icon' => 'i-lucide-wrench', 'tone' => 'warning'],
        ];

        $ordenes = $ordenesRecibidas()
            ->with([
                'cliente:id,razon_social',
                'vehiculo:id,placa,marca,modelo',
                'asignaciones' => fn ($query) => $query->where('activo', true)->with('mecanico:id,nombres,apellidos'),
            ])
            ->whereIn('estado', ['pendiente','asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba','finalizada','lista_entrega'])
            ->orderBy('recibida_en')
            ->get()
            ->groupBy('estado');

        $etapas = collect([
            'pendiente' => ['label' => 'Pendientes', 'icon' => 'i-lucide-inbox', 'tone' => 'neutral'],
            'asignada' => ['label' => 'Asignada', 'icon' => 'i-lucide-user-check', 'tone' => 'info'],
            'en_diagnostico' => ['label' => 'Diagnóstico', 'icon' => 'i-lucide-stethoscope', 'tone' => 'info'],
            'esperando_aprobacion' => ['label' => 'Esperando aprobación', 'icon' => 'i-lucide-hourglass', 'tone' => 'warning'],
            'esperando_repuestos' => ['label' => 'Esperando repuestos', 'icon' => 'i-lucide-package-clock', 'tone' => 'warning'],
            'en_reparacion' => ['label' => 'Reparación', 'icon' => 'i-lucide-wrench', 'tone' => 'warning'],
            'pausada' => ['label' => 'Pausada', 'icon' => 'i-lucide-pause', 'tone' => 'neutral'],
            'en_prueba' => ['label' => 'En prueba', 'icon' => 'i-lucide-gauge', 'tone' => 'info'],
            'finalizada' => ['label' => 'Finalizadas técnicamente', 'icon' => 'i-lucide-badge-check', 'tone' => 'success'],
            'lista_entrega' => ['label' => 'Listas para entrega', 'icon' => 'i-lucide-key-round', 'tone' => 'success'],
        ])->map(function (array $etapa, string $estado) use ($ordenes) {
            $items = $ordenes->get($estado, collect());

            return [...$etapa, 'estado' => $estado, 'total' => $items->count(), 'ordenes' => $items->take(5)->map(function ($orden) {
                $horas = max(0, (int) $orden->recibida_en->diffInHours(now()));

                return [
                    'id' => $orden->id,
                    'numero' => $orden->numero,
                    'placa' => $orden->vehiculo?->placa,
                    'vehiculo' => trim("{$orden->vehiculo?->marca} {$orden->vehiculo?->modelo}"),
                    'cliente' => $orden->cliente?->razon_social,
                    'falla' => $orden->falla_reportada,
                    'recibidaEn' => $orden->recibida_en,
                    'horas' => $horas,
                    'prioridad' => $horas >= 72 ? 'critica' : ($horas >= 24 ? 'atencion' : 'normal'),
                    'mecanicos' => $orden->asignaciones->map(fn ($asignacion) => trim("{$asignacion->mecanico?->nombres} {$asignacion->mecanico?->apellidos}"))->filter()->values(),
                ];
            })->values()];
        })->values();

        $proximasCitas = $usuario->can('citas.ver')
            ? CitaEloquentModel::query()->visiblePara($usuario)->with(['vehiculo:id,placa,marca,modelo', 'servicio:id,nombre'])
                ->where('inicio', '>=', now()->startOfDay())->whereNotIn('estado', ['cancelada', 'vencida'])->orderBy('inicio')->limit(5)->get()
                ->map(fn ($cita) => ['id' => $cita->id, 'numero' => $cita->numero, 'inicio' => $cita->inicio, 'placa' => $cita->vehiculo?->placa, 'vehiculo' => trim("{$cita->vehiculo?->marca} {$cita->vehiculo?->modelo}"), 'servicio' => $cita->servicio?->nombre, 'estado' => $cita->estado])
            : collect();

        $alertas = collect();
        if ($stockBajo) {
            $alertas->push(['title' => "{$stockBajo} referencias con stock bajo", 'description' => 'Revisa existencias antes de iniciar nuevas reparaciones.', 'icon' => 'i-lucide-package-x', 'tone' => 'warning', 'url' => '/inventario/catalogo-repuestos']);
        }
        $sinAsignar = $ordenesRecibidas()->whereIn('estado', ['pendiente','asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba'])->whereDoesntHave('asignaciones', fn (Builder $query) => $query->where('activo', true))->count();
        if ($sinAsignar && $usuario->can('ordenes.asignar')) {
            $alertas->push(['title' => "{$sinAsignar} órdenes sin mecánico", 'description' => 'Asigna responsables para evitar tiempos muertos.', 'icon' => 'i-lucide-user-round-x', 'tone' => 'error', 'url' => '/ordenes']);
        }
        $demoradas = $ordenesRecibidas()->whereIn('estado', ['pendiente','asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba'])->where('recibida_en', '<=', now()->subDays(3))->count();
        if ($demoradas) {
            $alertas->push(['title' => "{$demoradas} órdenes requieren atención", 'description' => 'Llevan más de 72 horas abiertas.', 'icon' => 'i-lucide-timer-off', 'tone' => 'error', 'url' => '/ordenes']);
        }

        return Inertia::render('Dashboard', [
            'metricas' => $metricas,
            'etapas' => $etapas,
            'proximasCitas' => $proximasCitas,
            'alertas' => $alertas,
            'usuario' => ['nombre' => $usuario->name, 'roles' => $usuario->getRoleNames()->values()],
        ]);
    }
}
