<?php

namespace Src\Taller\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;
use Src\Taller\Infrastructure\Requests\CambiarEstadoCatalogoRequest;
use Src\Taller\Infrastructure\Requests\GuardarEspecialidadRequest;
use Src\Taller\Infrastructure\Requests\GuardarServicioRequest;

class CatalogoTallerWebController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Taller/Catalogos', [
            'vista' => $request->route('vista') ?? 'especialidades',
            'especialidades' => EspecialidadEloquentModel::withCount(['mecanicos' => fn ($q) => $q->where('mecanico_especialidad.activo', true), 'servicios'])->orderBy('nombre')->paginate(10, ['*'], 'paginaEspecialidades')->withQueryString(),
            'especialidadesOpciones' => EspecialidadEloquentModel::where('estado', 'activo')->orderBy('nombre')->get(['id', 'nombre']),
            'servicios' => ServicioEloquentModel::with('especialidad:id,nombre')->orderBy('nombre')->paginate(10, ['*'], 'paginaServicios')->withQueryString()->through(fn ($s) => [
                'id' => $s->id, 'especialidad' => $s->especialidad?->nombre, 'especialidadId' => $s->especialidad_id,
                'codigo' => $s->codigo, 'nombre' => $s->nombre, 'descripcion' => $s->descripcion,
                'duracionMinutos' => $s->duracion_minutos, 'precioBase' => $s->precio_base, 'estado' => $s->estado,
            ]),
        ]);
    }

    public function storeEspecialidad(GuardarEspecialidadRequest $request, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $item = EspecialidadEloquentModel::create([...$request->validated(), 'estado' => 'activo', 'creado_por' => $request->user()->id, 'actualizado_por' => $request->user()->id]);
        $auditoria->registrar('especialidad.creada', 'especialidad', $item->id, [], $request);
        return back()->with('success', 'Especialidad creada exitosamente.');
    }

    public function estadoEspecialidad(CambiarEstadoCatalogoRequest $request, EspecialidadEloquentModel $especialidad, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $especialidad->update(['estado' => $request->validated('estado'), 'actualizado_por' => $request->user()->id]);
        $auditoria->registrar('especialidad.estado_cambiado', 'especialidad', $especialidad->id, ['estado' => $especialidad->estado], $request);
        return back()->with('success', 'Estado de especialidad actualizado.');
    }

    public function storeServicio(GuardarServicioRequest $request, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $item = ServicioEloquentModel::create([...$request->validated(), 'estado' => 'activo', 'creado_por' => $request->user()->id, 'actualizado_por' => $request->user()->id]);
        $auditoria->registrar('servicio.creado', 'servicio', $item->id, [], $request);
        return back()->with('success', 'Servicio creado exitosamente.');
    }

    public function estadoServicio(CambiarEstadoCatalogoRequest $request, ServicioEloquentModel $servicio, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $servicio->update(['estado' => $request->validated('estado'), 'actualizado_por' => $request->user()->id]);
        $auditoria->registrar('servicio.estado_cambiado', 'servicio', $servicio->id, ['estado' => $servicio->estado], $request);
        return back()->with('success', 'Estado del servicio actualizado.');
    }
}
