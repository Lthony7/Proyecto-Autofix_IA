<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Cita\Application\Services\VencerCitasFinalizadas;

final class VencerCitasFinalizadasCommand extends Command
{
    protected $signature = 'citas:vencer';

    protected $description = 'Marca como vencidas las citas cuyo horario terminó sin atención';

    public function handle(VencerCitasFinalizadas $vencer): int
    {
        $total = $vencer->ejecutar();
        $this->components->info("Citas marcadas como vencidas: {$total}");

        return self::SUCCESS;
    }
}
