<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('clientes')->where('tipo_documento', 'DNI')->update(['tipo_documento' => 'CC']);
        DB::table('clientes')->where('tipo_documento', 'RUC')->update(['tipo_documento' => 'NIT']);
        DB::statement("ALTER TABLE clientes ADD CONSTRAINT clientes_tipo_documento_check CHECK (tipo_documento IN ('CC', 'CE', 'NIT', 'PASAPORTE'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_tipo_documento_check');
    }
};
