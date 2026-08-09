<?php

namespace Src\OrdenTrabajo\Application\Services;

use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;

class AutorizarMecanicoOrden
{
    public function permite(UserEloquentModel $usuario, OrdenTrabajoEloquentModel $orden): bool
    {
        return $usuario->can('technicalWork', $orden);
    }

    public function autorizar(UserEloquentModel $usuario, OrdenTrabajoEloquentModel $orden): void
    {
        abort_unless($this->permite($usuario, $orden), 403, 'La orden no admite esta acción técnica o el usuario no está autorizado.');
    }
}
