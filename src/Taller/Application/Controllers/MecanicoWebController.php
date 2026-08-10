<?php

namespace Src\Taller\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Taller\Infrastructure\Models\DisponibilidadMecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
use Src\Taller\Infrastructure\Requests\CambiarEstadoCatalogoRequest;
use Src\Taller\Infrastructure\Requests\GuardarMecanicoRequest;

class MecanicoWebController extends Controller
{
    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->input('buscar'));
        $mecanicos = MecanicoEloquentModel::with(['especialidades' => fn ($q) => $q->where('mecanico_especialidad.activo', true), 'disponibilidades' => fn ($q) => $q->where('activo', true)])
            ->when($buscar, fn ($q) => $q->where(fn ($s) => $s->where('nombres', 'ilike', "%{$buscar}%")->orWhere('apellidos', 'ilike', "%{$buscar}%")->orWhere('numero_documento', 'ilike', "%{$buscar}%")))
            ->orderBy('apellidos')->paginate(10)->withQueryString();
        $mecanicos->through(fn ($m) => $this->toArray($m));
        return Inertia::render('Taller/Mecanicos/index', ['mecanicos' => $mecanicos, 'buscar' => $buscar]);
    }

    public function create(): Response { return Inertia::render('Taller/Mecanicos/form', ['mecanico' => null, 'especialidades' => $this->especialidades(), 'usuarios' => $this->usuarios()]); }
    public function edit(MecanicoEloquentModel $mecanico): Response
    {
        $mecanico->load(['especialidades' => fn ($q) => $q->where('mecanico_especialidad.activo', true), 'disponibilidades' => fn ($q) => $q->where('activo', true)]);
        return Inertia::render('Taller/Mecanicos/form', ['mecanico' => $this->toArray($mecanico), 'especialidades' => $this->especialidades(), 'usuarios' => $this->usuarios($mecanico->usuario_id)]);
    }

    public function store(GuardarMecanicoRequest $request, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $mecanico = DB::transaction(function () use ($request) {
            $datos = collect($request->validated())->except(['especialidad_ids', 'horarios'])->all();
            $mecanico = MecanicoEloquentModel::create([...$datos, 'estado' => 'activo', 'creado_por' => $request->user()->id, 'actualizado_por' => $request->user()->id]);
            $this->sincronizar($mecanico, $request->validated('especialidad_ids'), $request->validated('horarios'), $request->user()->id);
            return $mecanico;
        });
        $auditoria->registrar('mecanico.creado', 'mecanico', $mecanico->id, [], $request);
        return redirect()->route('mecanicos.index')->with('success', 'Mecánico registrado exitosamente.');
    }

    public function update(GuardarMecanicoRequest $request, MecanicoEloquentModel $mecanico, RegistrarAuditoria $auditoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $mecanico) {
            $mecanico->update([...collect($request->validated())->except(['especialidad_ids', 'horarios'])->all(), 'actualizado_por' => $request->user()->id]);
            $this->sincronizar($mecanico, $request->validated('especialidad_ids'), $request->validated('horarios'), $request->user()->id);
        });
        $auditoria->registrar('mecanico.actualizado', 'mecanico', $mecanico->id, [], $request);
        return redirect()->route('mecanicos.index')->with('success', 'Mecánico actualizado exitosamente.');
    }

    public function cambiarEstado(CambiarEstadoCatalogoRequest $request, MecanicoEloquentModel $mecanico, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $mecanico->update(['estado' => $request->validated('estado'), 'actualizado_por' => $request->user()->id]);
        if ($mecanico->estado !== 'activo') $mecanico->disponibilidades()->update(['activo' => false]);
        $auditoria->registrar('mecanico.estado_cambiado', 'mecanico', $mecanico->id, ['estado' => $mecanico->estado], $request);
        return back()->with('success', 'Estado actualizado.');
    }

    private function sincronizar(MecanicoEloquentModel $mecanico, array $especialidades, array $horarios, string $usuarioId): void
    {
        DB::table('mecanico_especialidad')->where('mecanico_id', $mecanico->id)->update(['activo' => false]);
        foreach ($especialidades as $id) {
            DB::table('mecanico_especialidad')->updateOrInsert(['mecanico_id' => $mecanico->id, 'especialidad_id' => $id], ['activo' => true, 'asignado_en' => now(), 'asignado_por' => $usuarioId]);
        }
        $mecanico->disponibilidades()->update(['activo' => false]);
        foreach ($horarios as $horario) {
            DisponibilidadMecanicoEloquentModel::updateOrCreate([
                'mecanico_id' => $mecanico->id, 'dia_semana' => $horario['diaSemana'], 'hora_inicio' => $horario['horaInicio'], 'hora_fin' => $horario['horaFin'],
            ], ['activo' => true, 'creado_por' => $usuarioId]);
        }
    }

    private function especialidades(): array { return EspecialidadEloquentModel::where('estado', 'activo')->orderBy('nombre')->get()->map(fn ($e) => ['label' => $e->nombre, 'value' => $e->id])->all(); }
    private function usuarios(?string $incluir = null): array
    {
        $ocupados = MecanicoEloquentModel::whereNotNull('usuario_id')
            ->when($incluir, fn ($q) => $q->where('usuario_id', '!=', $incluir))
            ->pluck('usuario_id');

        return UserEloquentModel::role('Mecánico')->where('activo', true)
            ->whereNotIn('id', $ocupados)
            ->orderBy('name')->get()->map(fn ($u) => ['label' => "{$u->name} · {$u->email}", 'value' => $u->id])->all();
    }
    private function toArray(MecanicoEloquentModel $m): array
    {
        return ['id' => $m->id, 'usuarioId' => $m->usuario_id, 'tipoDocumento' => $m->tipo_documento, 'numeroDocumento' => $m->numero_documento, 'nombres' => $m->nombres, 'apellidos' => $m->apellidos, 'telefono' => $m->telefono, 'email' => $m->email, 'fechaIngreso' => $m->fecha_ingreso?->format('Y-m-d'), 'estado' => $m->estado, 'especialidadIds' => $m->especialidades->pluck('id')->all(), 'especialidades' => $m->especialidades->pluck('nombre')->all(), 'horarios' => $m->disponibilidades->map(fn ($h) => ['diaSemana' => $h->dia_semana, 'horaInicio' => substr($h->hora_inicio, 0, 5), 'horaFin' => substr($h->hora_fin, 0, 5)])->all()];
    }
}
