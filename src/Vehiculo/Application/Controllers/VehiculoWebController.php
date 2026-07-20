<?php

namespace Src\Vehiculo\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
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
            ->paginate(15)
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

    public function store(GuardarVehiculoRequest $request, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $vehiculo = VehiculoEloquentModel::create([
            ...$request->validated(),
            'estado' => 'activo',
            'creado_por' => $request->user()->id,
            'actualizado_por' => $request->user()->id,
        ]);
        $auditoria->registrar('vehiculo.creado', 'vehiculo', $vehiculo->id, [], $request);

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
    ): RedirectResponse {
        $anteriores = $vehiculo->only(['cliente_id', 'placa', 'marca', 'modelo', 'anio', 'kilometraje']);
        $vehiculo->update([...$request->validated(), 'actualizado_por' => $request->user()->id]);
        $auditoria->registrar('vehiculo.actualizado', 'vehiculo', $vehiculo->id, ['antes' => $anteriores], $request);

        return redirect()->route('vehiculos.index')->with('success', 'Vehículo actualizado exitosamente.');
    }

    public function cambiarEstado(
        CambiarEstadoVehiculoRequest $request,
        VehiculoEloquentModel $vehiculo,
        RegistrarAuditoria $auditoria,
    ): RedirectResponse {
        $anterior = $vehiculo->estado;
        $vehiculo->update(['estado' => $request->validated('estado'), 'actualizado_por' => $request->user()->id]);
        $auditoria->registrar('vehiculo.estado_cambiado', 'vehiculo', $vehiculo->id, [
            'estado_anterior' => $anterior,
            'estado_nuevo' => $vehiculo->estado,
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
