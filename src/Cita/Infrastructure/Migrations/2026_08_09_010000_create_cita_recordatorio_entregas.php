<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $expected = (string) config('database.connections.pgsql.search_path');
        if (DB::getDriverName() === 'pgsql'
            && (DB::scalar('SELECT current_schema()') !== $expected
                || ! DB::scalar('SELECT current_schemas(false) = ARRAY[?]::name[]', [$expected]))) {
            throw new RuntimeException('La migración exige el DB_SCHEMA dedicado configurado y aislado.');
        }

        Schema::create('cita_recordatorio_entregas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cita_id')->constrained('citas')->restrictOnDelete();
            $table->timestampTz('inicio_programado');
            $table->string('canal', 20);
            $table->string('destinatario', 254);
            $table->timestampTz('encolado_en');
            $table->timestampTz('intentado_en')->nullable();
            $table->timestampTz('invalidado_en')->nullable();
            $table->timestamps();
            $table->unique(['cita_id', 'inicio_programado', 'canal'], 'cita_recordatorio_inicio_canal_unique');
            $table->index(['canal', 'intentado_en']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('El historial de recordatorios enviados no debe eliminarse.');
    }
};
