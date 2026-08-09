<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\Cita\Application\Services\VentanaRecordatorioCitas;
use Src\Cita\Infrastructure\Models\CitaRecordatorioEntregaEloquentModel;

final class RecordatorioCita extends Notification implements ShouldQueue
{
    use Queueable;

    private const ZONA_HORARIA = 'America/Guayaquil';

    public int $tries = 1;

    public function __construct(private readonly string $entregaId)
    {
    }

    public function via(object $notifiable): array
    {
        if (! config('autofix.appointment_reminders.enabled', false)) {
            $this->invalidar();

            return [];
        }

        $entrega = CitaRecordatorioEntregaEloquentModel::query()
            ->with('cita.cliente:id,email')
            ->find($this->entregaId);
        $cita = $entrega?->cita;
        $correoActual = mb_strtolower(trim((string) $cita?->cliente?->email));
        $esValida = $entrega !== null
            && $entrega->intentado_en === null
            && $entrega->invalidado_en === null
            && $cita !== null
            && $cita->inicio->equalTo($entrega->inicio_programado)
            && hash_equals($entrega->destinatario, $correoActual)
            && filter_var($correoActual, FILTER_VALIDATE_EMAIL) !== false
            && VentanaRecordatorioCitas::contiene(
                $cita->estado,
                $cita->inicio->setTimezone(self::ZONA_HORARIA),
                CarbonImmutable::now(self::ZONA_HORARIA),
                (int) config('autofix.appointment_reminders.window_minutes', 1440),
            );

        if (! $esValida) {
            $this->invalidar();

            return [];
        }

        $reservada = CitaRecordatorioEntregaEloquentModel::query()
            ->whereKey($this->entregaId)
            ->whereNull('intentado_en')
            ->whereNull('invalidado_en')
            ->update(['intentado_en' => CarbonImmutable::now(self::ZONA_HORARIA)]);

        return $reservada === 1 ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $entrega = CitaRecordatorioEntregaEloquentModel::query()
            ->with(['cita.cliente', 'cita.vehiculo'])
            ->findOrFail($this->entregaId);
        $cita = $entrega->cita;
        $inicio = $cita->inicio->setTimezone(self::ZONA_HORARIA);

        return (new MailMessage())
            ->subject("Recordatorio de cita {$cita->numero}")
            ->greeting('Hola,')
            ->line('Te recordamos que tienes una cita programada en AutoFix.')
            ->line('Fecha y hora: '.$inicio->locale('es')->translatedFormat('l, j \d\e F \d\e Y \a \l\a\s H:i').'.')
            ->line('Vehículo: '.$cita->vehiculo->placa.'.')
            ->line('Motivo: '.$cita->motivo.'.')
            ->line('Si necesitas cambiarla, comunícate directamente con el taller.')
            ->salutation('Equipo AutoFix');
    }

    private function invalidar(): void
    {
        CitaRecordatorioEntregaEloquentModel::query()
            ->whereKey($this->entregaId)
            ->whereNull('intentado_en')
            ->whereNull('invalidado_en')
            ->update(['invalidado_en' => CarbonImmutable::now(self::ZONA_HORARIA)]);
    }
}
