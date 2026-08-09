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

        $horarios = DisponibilidadMecanicoEloquentModel::where('mecanico_id', $mecanicoId)
            ->where('activo', true)->where('dia_semana', $inicio->dayOfWeekIso)
            ->where('hora_inicio', '<=', $inicio->format('H:i:s'))->where('hora_fin', '>=', $fin->format('H:i:s'))
            ->where(fn ($q) => $q->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $inicio->toDateString()))
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $inicio->toDateString()))->get();
        if ($horarios->isEmpty()) throw ValidationException::withMessages(['inicio' => 'La franja está fuera de la disponibilidad del mecánico.']);
        $alineado = $horarios->contains(function ($horario) use ($inicio) {
            $horaInicio = CarbonImmutable::parse($inicio->toDateString().' '.$horario->hora_inicio);
            return $horaInicio->diffInMinutes($inicio, false) >= 0 && $horaInicio->diffInMinutes($inicio, false) % 30 === 0;
        });
        if (! $alineado) throw ValidationException::withMessages(['inicio' => 'Selecciona una hora disponible de la lista.']);

        $solapada = CitaEloquentModel::where('mecanico_id', $mecanicoId)->whereNotIn('estado', ['cancelada', 'vencida'])
            ->when($ignorarCita, fn ($q) => $q->whereKeyNot($ignorarCita))
            ->where('inicio', '<', $fin)->where('fin', '>', $inicio)->exists();
        if ($solapada) throw ValidationException::withMessages(['inicio' => 'La franja seleccionada ya está ocupada.']);
    }
}
