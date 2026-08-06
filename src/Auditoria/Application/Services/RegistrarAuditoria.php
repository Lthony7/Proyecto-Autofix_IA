<?php

namespace Src\Auditoria\Application\Services;

use Illuminate\Http\Request;
use Src\Auditoria\Infrastructure\Models\AuditoriaEloquentModel;

class RegistrarAuditoria
{
    public function registrar(
        string $accion,
        string $recursoTipo,
        ?string $recursoId = null,
        array $cambios = [],
        ?Request $request = null,
        ?string $usuarioId = null,
    ): void {
        AuditoriaEloquentModel::create([
            'usuario_id' => $usuarioId ?? auth()->id(),
            'accion' => $accion,
            'recurso_tipo' => $recursoTipo,
            'recurso_id' => $recursoId,
            'cambios' => $cambios ?: null,
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 512) ?: null,
        ]);
    }
}
