<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas_ia', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('entrada_hash');
            $table->string('prompt_version', 40)->default('2026-08-04.v1')->after('version');
            $table->string('esquema_version', 40)->default('diagnostico.v2')->after('prompt_version');
            $table->text('respuesta_cruda')->nullable()->after('respuesta_original');
            $table->jsonb('meta_generacion')->nullable()->after('respuesta_cruda');
            $table->string('nivel_confianza', 20)->nullable()->after('prioridad');
            $table->string('nivel_riesgo', 20)->nullable()->after('nivel_confianza');
            $table->string('nivel_urgencia', 30)->nullable()->after('nivel_riesgo');
            $table->string('puede_circular_ia', 30)->nullable()->after('nivel_urgencia');
            $table->string('complejidad', 20)->nullable()->after('puede_circular_ia');
            $table->string('tiempo_estimado_diagnostico', 120)->nullable()->after('complejidad');
            $table->string('tiempo_estimado_reparacion', 120)->nullable()->after('tiempo_estimado_diagnostico');
            $table->foreignUuid('mecanico_sugerido_id')->nullable()->after('especialidad_sugerida_id')->constrained('mecanicos')->nullOnDelete();
            $table->index(['estado', 'created_at'], 'consultas_ia_estado_fecha_idx');
            $table->index(['especialidad_sugerida_id', 'created_at'], 'consultas_ia_especialidad_fecha_idx');
        });

        Schema::table('revisiones_sugerencia_ia', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('consulta_id');
            $table->boolean('coincide_ia')->nullable()->after('estado_nuevo');
            $table->text('observaciones_cliente')->nullable()->after('observaciones');
            $table->text('notas_internas')->nullable()->after('observaciones_cliente');
            $table->text('motivo_diferencia')->nullable()->after('notas_internas');
            $table->jsonb('pruebas_realizadas')->nullable()->after('motivo_diferencia');
        });
        DB::statement('WITH versiones AS (SELECT id, ROW_NUMBER() OVER (PARTITION BY consulta_id ORDER BY created_at, id) AS numero FROM revisiones_sugerencia_ia) UPDATE revisiones_sugerencia_ia SET version = versiones.numero FROM versiones WHERE revisiones_sugerencia_ia.id = versiones.id');
        Schema::table('revisiones_sugerencia_ia', fn (Blueprint $table) => $table->unique(['consulta_id', 'version'], 'revisiones_ia_consulta_version_unique'));

        Schema::table('consumos_ia', function (Blueprint $table) {
            $table->string('proveedor_intentado', 30)->nullable()->after('proveedor');
            $table->string('modelo_intentado', 120)->nullable()->after('modelo');
            $table->jsonb('meta')->nullable()->after('codigo_error');
        });

        DB::table('consultas_ia')->update(['prompt_version' => 'legacy.v1', 'esquema_version' => 'orientacion.v1']);
    }

    public function down(): void
    {
        Schema::table('consumos_ia', function (Blueprint $table) {
            $table->dropColumn(['proveedor_intentado', 'modelo_intentado', 'meta']);
        });
        Schema::table('revisiones_sugerencia_ia', function (Blueprint $table) {
            $table->dropUnique('revisiones_ia_consulta_version_unique');
            $table->dropColumn(['version', 'coincide_ia', 'observaciones_cliente', 'notas_internas', 'motivo_diferencia', 'pruebas_realizadas']);
        });
        Schema::table('consultas_ia', function (Blueprint $table) {
            $table->dropIndex('consultas_ia_estado_fecha_idx');
            $table->dropIndex('consultas_ia_especialidad_fecha_idx');
            $table->dropConstrainedForeignId('mecanico_sugerido_id');
            $table->dropColumn(['version', 'prompt_version', 'esquema_version', 'respuesta_cruda', 'meta_generacion', 'nivel_confianza', 'nivel_riesgo', 'nivel_urgencia', 'puede_circular_ia', 'complejidad', 'tiempo_estimado_diagnostico', 'tiempo_estimado_reparacion']);
        });
    }
};
