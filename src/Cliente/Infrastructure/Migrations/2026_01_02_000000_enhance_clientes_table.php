<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignUuid('usuario_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('activo')->index();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE clientes ADD CONSTRAINT clientes_estado_check CHECK (estado IN ('activo', 'inactivo', 'archivado'))");
        DB::statement('CREATE UNIQUE INDEX clientes_email_normalizado_unique ON clientes (LOWER(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clientes_email_normalizado_unique');
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actualizado_por');
            $table->dropConstrainedForeignId('creado_por');
            $table->dropConstrainedForeignId('usuario_id');
            $table->dropColumn('estado');
        });
    }
};
