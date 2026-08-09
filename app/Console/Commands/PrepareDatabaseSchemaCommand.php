<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\PrepareDatabaseSchema;
use Illuminate\Console\Command;

final class PrepareDatabaseSchemaCommand extends Command
{
    protected $signature = 'db:schema:prepare';

    protected $description = 'Crea y valida el esquema PostgreSQL dedicado configurado en DB_SCHEMA';

    public function handle(PrepareDatabaseSchema $preparer): int
    {
        $schema = $preparer->prepare();

        $this->components->info("Esquema PostgreSQL preparado: {$schema}");

        return self::SUCCESS;
    }
}
