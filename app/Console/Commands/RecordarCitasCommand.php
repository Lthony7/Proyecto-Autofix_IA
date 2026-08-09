<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Cita\Application\Services\EncolarRecordatoriosCitas;

final class RecordarCitasCommand extends Command
{
    protected $signature = 'citas:recordar';

    protected $description = 'Encola por correo los recordatorios de citas próximas';

    public function handle(EncolarRecordatoriosCitas $recordatorios): int
    {
        $total = $recordatorios->ejecutar();
        $this->components->info("Recordatorios de citas encolados: {$total}");

        return self::SUCCESS;
    }
}
