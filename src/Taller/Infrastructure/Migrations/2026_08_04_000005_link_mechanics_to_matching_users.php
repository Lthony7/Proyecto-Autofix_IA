<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE mecanicos AS mecanico SET usuario_id = usuario.id, updated_at = CURRENT_TIMESTAMP FROM users AS usuario WHERE mecanico.usuario_id IS NULL AND LOWER(mecanico.email) = LOWER(usuario.email) AND NOT EXISTS (SELECT 1 FROM mecanicos AS vinculado WHERE vinculado.usuario_id = usuario.id)');
    }

    public function down(): void {}
};
