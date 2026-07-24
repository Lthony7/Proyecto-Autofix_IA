<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pagos DROP CONSTRAINT pagos_estado_check');
        DB::statement('ALTER TABLE pagos DROP CONSTRAINT pagos_anulacion_check');
        DB::statement('ALTER TABLE pago_historial DROP CONSTRAINT pago_historial_evento_check');
        Schema::table('pagos', function (Blueprint $table) {
            $table->timestampTz('reembolsado_en')->nullable();
            $table->foreignUuid('reembolsado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo_reembolso')->nullable();
        });
        DB::statement("ALTER TABLE pagos ADD CONSTRAINT pagos_estado_check CHECK (estado IN ('registrado','anulado','reembolsado'))");
        DB::statement("ALTER TABLE pagos ADD CONSTRAINT pagos_anulacion_check CHECK ((estado = 'registrado' AND anulado_en IS NULL AND anulado_por IS NULL AND motivo_anulacion IS NULL AND reembolsado_en IS NULL AND reembolsado_por IS NULL AND motivo_reembolso IS NULL) OR (estado = 'anulado' AND anulado_en IS NOT NULL AND anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL AND reembolsado_en IS NULL AND reembolsado_por IS NULL AND motivo_reembolso IS NULL) OR (estado = 'reembolsado' AND anulado_en IS NULL AND anulado_por IS NULL AND motivo_anulacion IS NULL AND reembolsado_en IS NOT NULL AND reembolsado_por IS NOT NULL AND motivo_reembolso IS NOT NULL))");
        DB::statement("ALTER TABLE pago_historial ADD CONSTRAINT pago_historial_evento_check CHECK (evento IN ('registrado','anulado','reembolsado'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pagos DROP CONSTRAINT pagos_estado_check');
        DB::statement('ALTER TABLE pagos DROP CONSTRAINT pagos_anulacion_check');
        DB::statement('ALTER TABLE pago_historial DROP CONSTRAINT pago_historial_evento_check');
        DB::statement("UPDATE pagos SET estado = 'anulado', anulado_en = reembolsado_en, anulado_por = reembolsado_por, motivo_anulacion = CONCAT('Reembolso: ', motivo_reembolso) WHERE estado = 'reembolsado'");
        DB::statement("UPDATE pago_historial SET evento = 'anulado' WHERE evento = 'reembolsado'");
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reembolsado_por');
            $table->dropColumn(['reembolsado_en', 'motivo_reembolso']);
        });
        DB::statement("ALTER TABLE pagos ADD CONSTRAINT pagos_estado_check CHECK (estado IN ('registrado','anulado'))");
        DB::statement("ALTER TABLE pagos ADD CONSTRAINT pagos_anulacion_check CHECK ((estado = 'registrado' AND anulado_en IS NULL AND anulado_por IS NULL AND motivo_anulacion IS NULL) OR (estado = 'anulado' AND anulado_en IS NOT NULL AND anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL))");
        DB::statement("ALTER TABLE pago_historial ADD CONSTRAINT pago_historial_evento_check CHECK (evento IN ('registrado','anulado'))");
    }
};
