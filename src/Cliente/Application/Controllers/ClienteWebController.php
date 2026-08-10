<?php

namespace Src\Cliente\Application\Controllers;

use App\Http\Controllers\Controller;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Cliente\Infrastructure\Mappers\ClienteMapper;
use Src\Cliente\Infrastructure\Requests\StoreClienteRequest;
use Src\Cliente\Infrastructure\Requests\UpdateClienteRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Cliente\Infrastructure\Requests\CambiarEstadoClienteRequest;

class ClienteWebController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->input('buscar'));
        $estado = $request->input('estado');
        $query = ClienteEloquentModel::query()->withCount('vehiculos');

        $query->when($busqueda, fn ($q) => $q->where(function ($sub) use ($busqueda) {
            $sub->where('razon_social', 'ilike', "%{$busqueda}%")
                ->orWhere('numero_documento', 'ilike', "%{$busqueda}%")
                ->orWhere('email', 'ilike', "%{$busqueda}%");
        }));
        $query->when(in_array($estado, ['activo', 'inactivo', 'archivado'], true), fn ($q) => $q->where('estado', $estado));

        $clientes = $query->orderBy('razon_social')->paginate(10)->withQueryString();
        $clientes->through(fn ($cliente) => $this->toArray($cliente));

        return Inertia::render('Cliente/index', [
            'customers' => $clientes,
            'stats' => [
                'total' => ClienteEloquentModel::count(),
                'active' => ClienteEloquentModel::where('estado', 'activo')->count(),
                'inactive' => ClienteEloquentModel::where('estado', '!=', 'activo')->count(),
            ],
            'filters' => ['buscar' => $busqueda, 'estado' => $estado],
        ]);
    }

    /**
     * Mostrar formulario de creación
     */
    public function create(): Response
    {
        return Inertia::render('Cliente/create');
    }

    public function store(StoreClienteRequest $request, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $cliente = ClienteEloquentModel::create([
            ...$request->validated(),
            'estado' => 'activo',
            'creado_por' => $request->user()->id,
            'actualizado_por' => $request->user()->id,
        ]);
        $auditoria->registrar('cliente.creado', 'cliente', $cliente->id, [], $request);

        return redirect()->route('clientes.index')->with('success', 'Cliente creado exitosamente.');
    }

    public function edit(string $id): Response
    {
        $cliente = ClienteEloquentModel::findOrFail($id);

        return Inertia::render('Cliente/edit', [
            'cliente' => ClienteMapper::toDomain($cliente)->toArray()
        ]);
    }

    public function update(UpdateClienteRequest $request, string $id, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $cliente = ClienteEloquentModel::findOrFail($id);
        $anteriores = $cliente->only(['tipo_documento', 'numero_documento', 'razon_social', 'direccion', 'telefono', 'email']);
        $cliente->update([...$request->validated(), 'actualizado_por' => $request->user()->id]);
        $auditoria->registrar('cliente.actualizado', 'cliente', $cliente->id, ['antes' => $anteriores], $request);

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado exitosamente.');
    }

    public function cambiarEstado(
        CambiarEstadoClienteRequest $request,
        ClienteEloquentModel $cliente,
        RegistrarAuditoria $auditoria,
    ): RedirectResponse
    {
        $anterior = $cliente->estado;
        $cliente->update(['estado' => $request->validated('estado'), 'actualizado_por' => $request->user()->id]);
        $auditoria->registrar('cliente.estado_cambiado', 'cliente', $cliente->id, [
            'estado_anterior' => $anterior,
            'estado_nuevo' => $cliente->estado,
        ], $request);

        return back()->with('success', 'Estado del cliente actualizado.');
    }

    private function toArray(ClienteEloquentModel $cliente): array
    {
        return [
            'id' => $cliente->id,
            'tipoDocumento' => $cliente->tipo_documento,
            'numeroDocumento' => $cliente->numero_documento,
            'razonSocial' => $cliente->razon_social,
            'direccion' => $cliente->direccion,
            'telefono' => $cliente->telefono,
            'email' => $cliente->email,
            'estado' => $cliente->estado,
            'vehiculosCount' => $cliente->vehiculos_count ?? 0,
            'createdAt' => $cliente->created_at?->toIso8601String(),
            'updatedAt' => $cliente->updated_at?->toIso8601String(),
        ];
    }
}
