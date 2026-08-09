<?php

declare(strict_types=1);

namespace Src\Cita\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;
use Src\Cita\Infrastructure\Models\CitaEstadoHistorialEloquentModel;

final class VencerCitasFinalizadas
{
    private const EXPIRABLE_STATES = ['pendiente', 'confirmada', 'reprogramada'];

    public function ejecutar(int $limite = 100): int
    {
        $ahora = CarbonImmutable::now();
        $total = 0;

        do {
            $procesadas = DB::transaction(function () use ($ahora, $limite): int {
                $citas = CitaEloquentModel::query()
                    ->whereIn('estado', self::EXPIRABLE_STATES)
                    ->where('fin', '<=', $ahora)
                    ->orderBy('fin')
                    ->limit($limite)
                    ->lock('FOR UPDATE SKIP LOCKED')
                    ->get();

                foreach ($citas as $cita) {
                    $anterior = $cita->estado;
                    $datosAnteriores = [
                        'estado' => $anterior,
                        'inicio' => $cita->inicio->toIso8601String(),
                        'fin' => $cita->fin->toIso8601String(),
                        'mecanico_id' => $cita->mecanico_id,
                    ];

                    $cita->update(['estado' => 'vencida']);
                    CitaEstadoHistorialEloquentModel::create([
                        'cita_id' => $cita->id,
                        'estado_anterior' => $anterior,
                        'estado_nuevo' => 'vencida',
                        'observaciones' => 'Cita marcada automáticamente como vencida al finalizar su horario sin registrar atención.',
                        'datos_anteriores' => $datosAnteriores,
                        'usuario_id' => null,
                    ]);
                }

                return $citas->count();
            });

            $total += $procesadas;
        } while ($procesadas === $limite);

        return $total;
    }
}
