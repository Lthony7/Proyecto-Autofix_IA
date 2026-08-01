<?php

namespace Src\HistorialVehicular\Application\Services;

use Illuminate\Http\Request;
use Src\HistorialVehicular\Infrastructure\Models\HistorialVehiculoAccionEloquentModel;

class RegistrarEventoVehiculo
{
    public function registrar(
        string $vehiculoId,
        string $accion,
        string $descripcion,
        array $cambios = [],
        ?Request $request = null,
    ): HistorialVehiculoAccionEloquentModel {
        $usuario = $request?->user();

        return HistorialVehiculoAccionEloquentModel::create([
            'vehiculo_id' => $vehiculoId,
            'usuario_id' => $usuario?->id,
            'rol' => $usuario?->getRoleNames()->join(', ') ?: null,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'cambios' => $cambios ?: null,
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 512) ?: null,
        ]);
    }
}
