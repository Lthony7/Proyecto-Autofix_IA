<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE orden_servicios AS linea SET origen = 'cita' FROM ordenes_trabajo AS orden, citas AS cita WHERE linea.orden_id = orden.id AND orden.cita_id = cita.id AND linea.servicio_id = cita.servicio_id");
        foreach (DB::table('consultas_ia')->whereNotNull('orden_id')->get(['id', 'orden_id', 'respuesta_original', 'solicitada_por']) as $consulta) {
            $respuesta = is_string($consulta->respuesta_original) ? json_decode($consulta->respuesta_original, true) : (array) $consulta->respuesta_original;
            foreach ($respuesta['repuestos_posibles'] ?? [] as $item) {
                $nombre = trim((string) ($item['nombre'] ?? ''));
                if ($nombre === '' || DB::table('orden_repuestos_requeridos')->where('orden_id', $consulta->orden_id)->where('origen', 'ia')->whereRaw('LOWER(descripcion) = ?', [mb_strtolower($nombre)])->exists()) continue;
                $repuestoId = DB::table('repuestos')->where('estado', 'activo')->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->value('id');
                DB::table('orden_repuestos_requeridos')->insert(['id' => (string) Str::uuid(), 'orden_id' => $consulta->orden_id, 'repuesto_id' => $repuestoId, 'origen' => 'ia', 'descripcion' => $nombre, 'cantidad' => $item['cantidad'] ?? 1, 'motivo' => $item['motivo'] ?? 'Recomendación preliminar de IA pendiente de confirmación.', 'estado' => 'sugerido', 'agregado_por' => $consulta->solicitada_por, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void {}
};
