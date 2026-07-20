<?php

namespace Src\Cita\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;
use Src\Taller\Infrastructure\Models\DisponibilidadMecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;

class ValidadorDisponibilidad
{
    public function validar(string $mecanicoId, CarbonImmutable $inicio, CarbonImmutable $fin, ?string $ignorarCita = null): void
    {
        $mecanico = MecanicoEloquentModel::whereKey($mecanicoId)->where('estado', 'activo')->first();
        if (! $mecanico) throw ValidationException::withMessages(['mecanicoId' => 'El mecánico no está disponible.']);

        $cubreHorario = DisponibilidadMecanicoEloquentModel::where('mecanico_id', $mecanicoId)
            ->where('activo', true)->where('dia_semana', $inicio->dayOfWeekIso)
            ->where('hora_inicio', '<=', $inicio->format('H:i:s'))->where('hora_fin', '>=', $fin->format('H:i:s'))
            ->where(fn ($q) => $q->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $inicio->toDateString()))
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $inicio->toDateString()))->exists();
        if (! $cubreHorario) throw ValidationException::withMessages(['inicio' => 'La franja está fuera de la disponibilidad del mecánico.']);

        $solapada = CitaEloquentModel::where('mecanico_id', $mecanicoId)->whereNotIn('estado', ['cancelada'])
            ->when($ignorarCita, fn ($q) => $q->whereKeyNot($ignorarCita))
            ->where('inicio', '<', $fin)->where('fin', '>', $inicio)->exists();
        if ($solapada) throw ValidationException::withMessages(['inicio' => 'La franja seleccionada ya está ocupada.']);
    }
}
