<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ConsecutivoDocumentos
{
    public function siguiente(string $clave, string $prefijo): string
    {
        $registro = DB::table('consecutivos_documento')->where('clave', $clave)->lockForUpdate()->first();
        if (! $registro) {
            DB::table('consecutivos_documento')->insert([
                'clave' => $clave, 'prefijo' => $prefijo, 'ultimo' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $registro = DB::table('consecutivos_documento')->where('clave', $clave)->lockForUpdate()->firstOrFail();
        }

        $siguiente = (int) $registro->ultimo + 1;
        DB::table('consecutivos_documento')->where('clave', $clave)->update([
            'ultimo' => $siguiente, 'updated_at' => now(),
        ]);

        return $registro->prefijo.'-'.str_pad((string) $siguiente, 8, '0', STR_PAD_LEFT);
    }
}
