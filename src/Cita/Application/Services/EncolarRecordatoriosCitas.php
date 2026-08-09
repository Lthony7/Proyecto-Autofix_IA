<?php

declare(strict_types=1);

namespace Src\Cita\Application\Services;

use App\Notifications\RecordatorioCita;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;

final class EncolarRecordatoriosCitas
{
    private const ZONA_HORARIA = 'America/Bogota';

    public function ejecutar(int $limite = 100): int
    {
        if (! config('autofix.appointment_reminders.enabled', false)) {
            return 0;
        }

        $ahora = CarbonImmutable::now(self::ZONA_HORARIA);
        $hasta = $ahora->addMinutes(max(1, (int) config('autofix.appointment_reminders.window_minutes', 1440)));
        $total = 0;

        do {
            $citas = CitaEloquentModel::query()
                ->select('citas.*')
                ->with('cliente:id,email')
                ->whereIn('estado', VentanaRecordatorioCitas::ESTADOS_ELEGIBLES)
                ->where('inicio', '>', $ahora)
                ->where('inicio', '<=', $hasta)
                ->whereHas('cliente', fn ($query) => $query->whereNotNull('email')->where('email', '<>', ''))
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('cita_recordatorio_entregas')
                        ->whereColumn('cita_recordatorio_entregas.cita_id', 'citas.id')
                        ->whereColumn('cita_recordatorio_entregas.inicio_programado', 'citas.inicio')
                        ->where('cita_recordatorio_entregas.canal', 'email');
                })
                ->orderBy('inicio')
                ->limit($limite)
                ->get();

            $encoladas = 0;
            $registradas = 0;
            foreach ($citas as $cita) {
                $correo = mb_strtolower(trim((string) $cita->cliente->email));
                $correoValido = filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;

                $id = (string) Str::uuid();
                $insertada = DB::table('cita_recordatorio_entregas')->insertOrIgnore([
                    'id' => $id,
                    'cita_id' => $cita->id,
                    'inicio_programado' => $cita->inicio,
                    'canal' => 'email',
                    'destinatario' => $correo,
                    'encolado_en' => $ahora,
                    'invalidado_en' => $correoValido ? null : $ahora,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                if ($insertada === 1 && $correoValido) {
                    Notification::route('mail', $correo)->notify(new RecordatorioCita($id));
                    $encoladas++;
                }

                $registradas += $insertada;
            }

            $total += $encoladas;
        } while ($citas->count() === $limite && $registradas > 0);

        return $total;
    }
}
