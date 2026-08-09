<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PrepareDatabaseSchema
{
    public function prepare(): string
    {
        $connectionName = (string) config('database.default');
        $driver = (string) config("database.connections.{$connectionName}.driver");
        $schema = (string) config("database.connections.{$connectionName}.search_path");

        if ($driver !== 'pgsql') {
            throw new RuntimeException('La aplicación exige PostgreSQL para ejecutar migraciones.');
        }

        if ($schema === '' || $schema === 'public' || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $schema) !== 1) {
            throw new RuntimeException('DB_SCHEMA debe ser un identificador PostgreSQL válido distinto de public.');
        }

        $connection = DB::connection($connectionName);
        $exists = $connection->scalar(
            'SELECT EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = ?)',
            [$schema],
        );

        if (! $exists) {
            $connection->statement(sprintf('CREATE SCHEMA "%s"', $schema));
        }

        $this->assertIsolatedSearchPath($connection, $schema);

        return $schema;
    }

    private function assertIsolatedSearchPath(ConnectionInterface $connection, string $schema): void
    {
        $currentSchema = $connection->scalar('SELECT current_schema()');
        $isolated = $connection->scalar(
            'SELECT current_schemas(false) = ARRAY[?]::name[]',
            [$schema],
        );

        if ($currentSchema !== $schema || ! $isolated) {
            throw new RuntimeException(
                "El search_path debe resolver exclusivamente el esquema configurado '{$schema}'.",
            );
        }
    }
}
