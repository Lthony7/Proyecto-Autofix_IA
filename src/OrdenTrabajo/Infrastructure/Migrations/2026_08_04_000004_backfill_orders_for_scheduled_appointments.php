<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $citas = DB::table('citas')->whereNotIn('estado', ['cancelada'])->whereNotExists(fn ($q) => $q->selectRaw('1')->from('ordenes_trabajo')->whereColumn('ordenes_trabajo.cita_id', 'citas.id'))->get();
            foreach ($citas as $cita) {
                $ordenId = (string) Str::uuid();
                $ahora = now();
                DB::table('ordenes_trabajo')->insert(['id' => $ordenId, 'numero' => 'OT-'.$ahora->format('Ymd').'-'.mb_strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 6)), 'cita_id' => $cita->id, 'cliente_id' => $cita->cliente_id, 'vehiculo_id' => $cita->vehiculo_id, 'falla_reportada' => $cita->motivo, 'kilometraje' => $cita->kilometraje, 'estado' => 'pendiente', 'recibida_en' => $cita->inicio, 'creado_por' => $cita->creado_por, 'actualizado_por' => $cita->actualizado_por, 'created_at' => $ahora, 'updated_at' => $ahora]);
                if ($cita->servicio_id) {
                    $servicio = DB::table('servicios_taller')->where('id', $cita->servicio_id)->first();
                    if ($servicio) DB::table('orden_servicios')->insert(['id' => (string) Str::uuid(), 'orden_id' => $ordenId, 'servicio_id' => $servicio->id, 'nombre_servicio' => $servicio->nombre, 'precio_acordado' => $servicio->precio_base, 'estado' => 'pendiente', 'created_at' => $ahora, 'updated_at' => $ahora]);
                }
                if ($cita->mecanico_id) DB::table('orden_mecanicos')->insert(['id' => (string) Str::uuid(), 'orden_id' => $ordenId, 'mecanico_id' => $cita->mecanico_id, 'activo' => true, 'asignado_en' => $ahora, 'asignado_por' => $cita->creado_por, 'observaciones' => 'Asignado automáticamente desde una cita existente.']);
                DB::table('orden_estado_historial')->insert(['id' => (string) Str::uuid(), 'orden_id' => $ordenId, 'estado_nuevo' => 'pendiente', 'observaciones' => 'Orden recuperada automáticamente desde una cita existente.', 'usuario_id' => $cita->creado_por, 'created_at' => $ahora]);
            }
        });
    }

    public function down(): void {}
};
