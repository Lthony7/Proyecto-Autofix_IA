<?php

namespace Src\Cita\Application\Services;

use Carbon\CarbonImmutable;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;
use Src\Taller\Infrastructure\Models\DisponibilidadMecanicoEloquentModel;

class GeneradorSlotsDisponibles
{
    public function generar(string $mecanicoId, int $duracionMinutos, ?string $ignorarCita = null, int $horizonteDias = 90): array
    {
        $ahora = CarbonImmutable::now(config('app.timezone'));
        $inicioPeriodo = $ahora->startOfDay();
        $finPeriodo = $inicioPeriodo->addDays($horizonteDias - 1)->endOfDay();
        $horarios = DisponibilidadMecanicoEloquentModel::where('mecanico_id', $mecanicoId)
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNull('vigente_desde')->orWhereDate('vigente_desde', '<=', $finPeriodo->toDateString()))
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $inicioPeriodo->toDateString()))
            ->get();
        $ocupaciones = CitaEloquentModel::where('mecanico_id', $mecanicoId)
            ->whereNotIn('estado', ['cancelada', 'vencida'])
            ->when($ignorarCita, fn ($q) => $q->whereKeyNot($ignorarCita))
            ->where('fin', '>', $inicioPeriodo)
            ->where('inicio', '<=', $finPeriodo)
            ->get(['inicio', 'fin']);

        $fechas = [];
        for ($i = 0; $i < $horizonteDias; $i++) {
            $fecha = $inicioPeriodo->addDays($i);
            $slots = [];
            foreach ($horarios->where('dia_semana', $fecha->dayOfWeekIso) as $horario) {
                if ($horario->vigente_desde && $fecha->isBefore($horario->vigente_desde->startOfDay())) continue;
                if ($horario->vigente_hasta && $fecha->isAfter($horario->vigente_hasta->endOfDay())) continue;
                $cursor = $fecha->setTimeFromTimeString(substr($horario->hora_inicio, 0, 8));
                $limite = $fecha->setTimeFromTimeString(substr($horario->hora_fin, 0, 8));
                while ($cursor->addMinutes($duracionMinutos)->lessThanOrEqualTo($limite)) {
                    $fin = $cursor->addMinutes($duracionMinutos);
                    $ocupado = $ocupaciones->contains(fn ($cita) => $cita->inicio->lessThan($fin) && $cita->fin->greaterThan($cursor));
                    if ($cursor->greaterThan($ahora) && ! $ocupado) $slots[$cursor->format('H:i')] = true;
                    $cursor = $cursor->addMinutes(30);
                }
            }
            if ($slots) {
                $horas = array_keys($slots);
                sort($horas);
                $fechas[] = ['value' => $fecha->toDateString(), 'horas' => $horas];
            }
        }

        return $fechas;
    }
}
