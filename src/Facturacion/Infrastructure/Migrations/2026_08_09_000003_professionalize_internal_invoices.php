<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || DB::scalar('select current_schema()') === 'public') {
            throw new RuntimeException('La migración exige PostgreSQL y un DB_SCHEMA dedicado distinto de public.');
        }

        Schema::create('consecutivos_documento', function (Blueprint $table) {
            $table->string('clave', 40)->primary();
            $table->string('prefijo', 10);
            $table->unsignedBigInteger('ultimo')->default(0);
            $table->timestampsTz();
        });
        DB::table('consecutivos_documento')->insert([
            ['clave' => 'factura_interna', 'prefijo' => 'FAC', 'ultimo' => DB::table('facturas_orden')->count(), 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'pago', 'prefijo' => 'PG', 'ultimo' => DB::table('pagos')->count(), 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'comprobante_pago', 'prefijo' => 'RC', 'ultimo' => DB::table('pagos')->count(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('facturas_orden', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
            $table->foreignUuid('reemplaza_factura_id')->nullable()->constrained('facturas_orden')->nullOnDelete();
            $table->index(['orden_id', 'version']);
        });
        DB::statement('ALTER TABLE facturas_orden ADD CONSTRAINT facturas_version_check CHECK (version > 0)');
        DB::statement("ALTER TABLE facturas_orden ADD CONSTRAINT facturas_descuento_autorizacion_check CHECK ((descuento = 0 AND motivo_descuento IS NULL AND descuento_autorizado_por IS NULL AND descuento_autorizado_en IS NULL) OR (descuento > 0 AND NULLIF(BTRIM(motivo_descuento), '') IS NOT NULL AND descuento_autorizado_por IS NOT NULL AND descuento_autorizado_en IS NOT NULL))");
        DB::statement('ALTER TABLE factura_orden_lineas ADD CONSTRAINT factura_lineas_calculo_check CHECK (subtotal = ROUND(cantidad * precio_unitario, 2))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE factura_orden_lineas DROP CONSTRAINT IF EXISTS factura_lineas_calculo_check');
        DB::statement('ALTER TABLE facturas_orden DROP CONSTRAINT IF EXISTS facturas_descuento_autorizacion_check');
        DB::statement('ALTER TABLE facturas_orden DROP CONSTRAINT IF EXISTS facturas_version_check');
        Schema::table('facturas_orden', function (Blueprint $table) {
            $table->dropIndex(['orden_id', 'version']);
            $table->dropConstrainedForeignId('reemplaza_factura_id');
            $table->dropColumn('version');
        });
        Schema::dropIfExists('consecutivos_documento');
    }
};
