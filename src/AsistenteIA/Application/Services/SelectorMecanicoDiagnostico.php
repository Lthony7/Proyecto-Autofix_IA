<?php

namespace Src\AsistenteIA\Application\Services;

use Illuminate\Support\Collection;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;

class SelectorMecanicoDiagnostico
{
    public function candidatos(?EspecialidadEloquentModel $especialidad, int $limite = 5): Collection
    {
        if (! $especialidad) return collect();

        return MecanicoEloquentModel::query()
            ->where('estado', 'activo')
            ->whereHas('especialidades', fn ($q) => $q->whereKey($especialidad->id)->where('mecanico_especialidad.activo', true))
            ->with(['especialidades' => fn ($q) => $q->where('mecanico_especialidad.activo', true)])
            ->withCount([
                'asignaciones as ordenes_activas' => fn ($q) => $q->where('activo', true)->whereHas('orden', fn ($o) => $o->whereIn('estado', ['pendiente', 'en_diagnostico', 'en_reparacion', 'finalizada'])),
                'citas as citas_futuras' => fn ($q) => $q->where('inicio', '>=', now())->whereNotIn('estado', ['cancelada', 'atendida']),
                'disponibilidades as horarios_activos' => fn ($q) => $q->where('activo', true),
            ])
            ->orderByDesc('horarios_activos')
            ->orderBy('ordenes_activas')
            ->orderBy('citas_futuras')
            ->orderBy('apellidos')
            ->limit($limite)
            ->get();
    }
}
