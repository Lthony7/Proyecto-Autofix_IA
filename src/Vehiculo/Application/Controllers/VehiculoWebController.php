<?php

namespace Src\Vehiculo\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\HistorialVehicular\Application\Services\RegistrarEventoVehiculo;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;
use Src\Vehiculo\Infrastructure\Requests\CambiarEstadoVehiculoRequest;
use Src\Vehiculo\Infrastructure\Requests\GuardarVehiculoRequest;

class VehiculoWebController extends Controller
{
    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->input('buscar'));
        $estado = $request->input('estado');
        $vehiculos = VehiculoEloquentModel::query()
            ->with('cliente:id,razon_social,numero_documento')
            ->visiblePara($request->user())
            ->when($buscar, fn ($q) => $q->where(function ($sub) use ($buscar) {
                $sub->where('placa_normalizada', 'ilike', '%'.preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($buscar)).'%')
                    ->orWhere('marca', 'ilike', "%{$buscar}%")
                    ->orWhere('modelo', 'ilike', "%{$buscar}%")
                    ->orWhereHas('cliente', fn ($cliente) => $cliente->where('razon_social', 'ilike', "%{$buscar}%"));
            }))
            ->when(in_array($estado, ['activo', 'inactivo', 'archivado'], true), fn ($q) => $q->where('estado', $estado))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $vehiculos->through(fn ($vehiculo) => $this->toArray($vehiculo));

        return Inertia::render('Vehiculo/index', [
            'vehiculos' => $vehiculos,
            'filters' => ['buscar' => $buscar, 'estado' => $estado],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Vehiculo/form', [
            'vehiculo' => null,
            'clientes' => $this->clientesActivos(),
        ]);
    }

    public function store(GuardarVehiculoRequest $request, RegistrarAuditoria $auditoria, RegistrarEventoVehiculo $historial): RedirectResponse
    {
        $vehiculo = VehiculoEloquentModel::create([
            ...$request->validated(),
            'estado' => 'activo',
            'creado_por' => $request->user()->id,
            'actualizado_por' => $request->user()->id,
        ]);
        $auditoria->registrar('vehiculo.creado', 'vehiculo', $vehiculo->id, [], $request);
        $historial->registrar($vehiculo->id, 'vehiculo.creado', 'Vehículo registrado en el sistema.', [
            'despues' => $vehiculo->only(['cliente_id', 'placa', 'marca', 'modelo', 'anio', 'color', 'kilometraje', 'combustible', 'estado']),
        ], $request);

        return redirect()->route('vehiculos.index')->with('success', 'Vehículo registrado exitosamente.');
    }

    public function edit(VehiculoEloquentModel $vehiculo): Response
    {
        return Inertia::render('Vehiculo/form', [
            'vehiculo' => $this->toArray($vehiculo),
            'clientes' => $this->clientesActivos($vehiculo->cliente_id),
        ]);
    }

    public function update(
        GuardarVehiculoRequest $request,
        VehiculoEloquentModel $vehiculo,
        RegistrarAuditoria $auditoria,
        RegistrarEventoVehiculo $historial,
    ): RedirectResponse {
        $campos = ['cliente_id', 'placa', 'marca', 'modelo', 'anio', 'color', 'kilometraje', 'combustible', 'observaciones'];
        $anteriores = $vehiculo->only($campos);
        $vehiculo->update([...$request->validated(), 'actualizado_por' => $request->user()->id]);
        $posteriores = $vehiculo->only($campos);
        $cambios = collect($posteriores)->filter(fn ($valor, $campo) => $anteriores[$campo] !== $valor)
            ->mapWithKeys(fn ($valor, $campo) => [$campo => ['antes' => $anteriores[$campo], 'despues' => $valor]])->all();
        $auditoria->registrar('vehiculo.actualizado', 'vehiculo', $vehiculo->id, ['cambios' => $cambios], $request);
        if (array_key_exists('cliente_id', $cambios)) {
            $historial->registrar($vehiculo->id, 'vehiculo.propietario_cambiado', 'Se cambió el propietario asociado al vehículo.', $cambios, $request);
            unset($cambios['cliente_id']);
        }
        if ($cambios) {
            $historial->registrar($vehiculo->id, 'vehiculo.actualizado', 'Se actualizó la información del vehículo.', $cambios, $request);
        }

        return redirect()->route('vehiculos.index')->with('success', 'Vehículo actualizado exitosamente.');
    }

    public function cambiarEstado(
        CambiarEstadoVehiculoRequest $request,
        VehiculoEloquentModel $vehiculo,
        RegistrarAuditoria $auditoria,
        RegistrarEventoVehiculo $historial,
    ): RedirectResponse {
        $anterior = $vehiculo->estado;
        $vehiculo->update(['estado' => $request->validated('estado'), 'actualizado_por' => $request->user()->id]);
        $auditoria->registrar('vehiculo.estado_cambiado', 'vehiculo', $vehiculo->id, [
            'estado_anterior' => $anterior,
            'estado_nuevo' => $vehiculo->estado,
        ], $request);
        $historial->registrar($vehiculo->id, 'vehiculo.estado_cambiado', "El estado cambió de {$anterior} a {$vehiculo->estado}.", [
            'estado' => ['antes' => $anterior, 'despues' => $vehiculo->estado],
        ], $request);

        return back()->with('success', 'Estado del vehículo actualizado.');
    }

    private function clientesActivos(?string $incluir = null): array
    {
        return ClienteEloquentModel::query()
            ->where(fn ($q) => $q->where('estado', 'activo')->when($incluir, fn ($sub) => $sub->orWhere('id', $incluir)))
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'numero_documento'])
            ->map(fn ($cliente) => [
                'label' => "{$cliente->razon_social} · {$cliente->numero_documento}",
                'value' => $cliente->id,
            ])->all();
    }

    private function toArray(VehiculoEloquentModel $vehiculo): array
    {
        return [
            'id' => $vehiculo->id,
            'clienteId' => $vehiculo->cliente_id,
            'cliente' => $vehiculo->relationLoaded('cliente') ? $vehiculo->cliente?->razon_social : null,
            'placa' => $vehiculo->placa,
            'marca' => $vehiculo->marca,
            'modelo' => $vehiculo->modelo,
            'anio' => $vehiculo->anio,
            'color' => $vehiculo->color,
            'kilometraje' => $vehiculo->kilometraje,
            'combustible' => $vehiculo->combustible,
            'observaciones' => $vehiculo->observaciones,
            'estado' => $vehiculo->estado,
        ];
    }
}
