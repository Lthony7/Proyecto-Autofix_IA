<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_repuesto', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('nombre',120)->unique(); $table->text('descripcion')->nullable(); $table->string('estado',20)->default('activo')->index();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete(); $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('proveedores', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('documento',40)->unique(); $table->string('nombre',180); $table->string('contacto',120)->nullable(); $table->string('telefono',30)->nullable(); $table->string('email')->nullable(); $table->string('estado',20)->default('activo')->index();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete(); $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('repuestos', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('categoria_id')->constrained('categorias_repuesto')->restrictOnDelete(); $table->foreignUuid('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete();
            $table->string('codigo',50)->unique(); $table->string('nombre',180); $table->text('descripcion')->nullable(); $table->string('unidad',20)->default('unidad');
            $table->decimal('stock_actual',14,3)->default(0); $table->decimal('stock_minimo',14,3)->default(0); $table->decimal('costo_referencia',14,2)->default(0); $table->decimal('precio_venta',14,2)->default(0); $table->string('estado',20)->default('activo')->index();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete(); $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['categoria_id','estado']);
        });
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('repuesto_id')->constrained('repuestos')->restrictOnDelete(); $table->foreignUuid('orden_id')->nullable()->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->string('tipo',20); $table->decimal('cantidad',14,3); $table->decimal('stock_anterior',14,3); $table->decimal('stock_resultante',14,3); $table->decimal('costo_unitario',14,2)->nullable(); $table->text('motivo');
            $table->uuid('movimiento_origen_id')->nullable()->unique(); $table->foreignUuid('registrado_por')->constrained('users')->restrictOnDelete(); $table->timestampTz('created_at')->useCurrent(); $table->index(['repuesto_id','created_at']);
        });
        Schema::table('movimientos_inventario', function (Blueprint $table) { $table->foreign('movimiento_origen_id')->references('id')->on('movimientos_inventario')->restrictOnDelete(); });
        Schema::create('orden_repuestos', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('orden_id')->constrained('ordenes_trabajo')->restrictOnDelete(); $table->foreignUuid('repuesto_id')->constrained('repuestos')->restrictOnDelete(); $table->decimal('cantidad',14,3); $table->decimal('precio_unitario',14,2); $table->foreignUuid('movimiento_salida_id')->unique()->constrained('movimientos_inventario')->restrictOnDelete();
            $table->timestampTz('revertido_en')->nullable(); $table->foreignUuid('revertido_por')->nullable()->constrained('users')->nullOnDelete(); $table->foreignUuid('registrado_por')->constrained('users')->restrictOnDelete(); $table->timestampTz('created_at')->useCurrent(); $table->index(['orden_id','revertido_en']);
        });
        foreach(['categorias_repuesto','proveedores','repuestos']as$t)DB::statement("ALTER TABLE {$t} ADD CONSTRAINT {$t}_estado_check CHECK (estado IN ('activo','inactivo','archivado'))");
        DB::statement('ALTER TABLE repuestos ADD CONSTRAINT repuestos_stock_check CHECK (stock_actual >= 0 AND stock_minimo >= 0)'); DB::statement('ALTER TABLE repuestos ADD CONSTRAINT repuestos_precios_check CHECK (costo_referencia >= 0 AND precio_venta >= 0)');
        DB::statement("ALTER TABLE movimientos_inventario ADD CONSTRAINT movimientos_tipo_check CHECK (tipo IN ('entrada','salida','ajuste','reversion'))");
        DB::statement("ALTER TABLE movimientos_inventario ADD CONSTRAINT movimientos_cantidad_check CHECK (cantidad <> 0 AND stock_resultante >= 0 AND stock_resultante = stock_anterior + cantidad AND (tipo <> 'entrada' OR cantidad > 0) AND (tipo <> 'salida' OR cantidad < 0) AND (costo_unitario IS NULL OR costo_unitario >= 0))");
        DB::statement('ALTER TABLE orden_repuestos ADD CONSTRAINT orden_repuestos_valores_check CHECK (cantidad > 0 AND precio_unitario >= 0)');
    }
    public function down(): void { Schema::dropIfExists('orden_repuestos');Schema::dropIfExists('movimientos_inventario');Schema::dropIfExists('repuestos');Schema::dropIfExists('proveedores');Schema::dropIfExists('categorias_repuesto'); }
};
