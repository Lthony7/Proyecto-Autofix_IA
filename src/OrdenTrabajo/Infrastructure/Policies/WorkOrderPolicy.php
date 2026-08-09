<?php

declare(strict_types=1);

namespace Src\OrdenTrabajo\Infrastructure\Policies;

use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;

final class WorkOrderPolicy
{
    private const CLOSED_STATES = ['finalizada', 'lista_entrega', 'entregada', 'cancelada'];

    public function viewAny(UserEloquentModel $user): bool
    {
        return $user->can('ordenes.ver');
    }

    public function view(UserEloquentModel $user, OrdenTrabajoEloquentModel $order): bool
    {
        if (! $user->can('ordenes.ver')) {
            return false;
        }

        if ($this->hasAdministrativeScope($user)) {
            return true;
        }

        if ($user->can('ordenes.ver_asignadas') && $this->isAssignedMechanic($user, $order)) {
            return true;
        }

        return $user->cliente()->whereKey($order->cliente_id)->exists();
    }

    public function technicalWork(UserEloquentModel $user, OrdenTrabajoEloquentModel $order): bool
    {
        return ! $this->isClosed($order)
            && ($user->can('ordenes.administrar') || $this->isAssignedMechanic($user, $order));
    }

    public function correctDiagnosis(UserEloquentModel $user, OrdenTrabajoEloquentModel $order): bool
    {
        return in_array($order->estado, ['finalizada', 'lista_entrega', 'entregada'], true)
            && $user->can('diagnosticos.corregir')
            && ($user->can('ordenes.administrar') || $this->isAssignedMechanic($user, $order));
    }

    public function mutate(UserEloquentModel $user, OrdenTrabajoEloquentModel $order): bool
    {
        return ! $this->isClosed($order) && $this->view($user, $order);
    }

    public function assign(UserEloquentModel $user, OrdenTrabajoEloquentModel $order): bool
    {
        return $user->can('ordenes.asignar') && $this->mutate($user, $order);
    }

    public function deliver(UserEloquentModel $user, OrdenTrabajoEloquentModel $order): bool
    {
        return $user->can('ordenes.entregar')
            && in_array($order->estado, ['finalizada', 'lista_entrega'], true)
            && $this->view($user, $order);
    }

    public function cancel(UserEloquentModel $user, OrdenTrabajoEloquentModel $order): bool
    {
        return $user->can('ordenes.cancelar') && $this->mutate($user, $order);
    }

    private function hasAdministrativeScope(UserEloquentModel $user): bool
    {
        return $user->can('ordenes.administrar')
            || $user->hasAnyPermission(['ordenes.crear', 'ordenes.asignar', 'ordenes.entregar', 'ordenes.cancelar']);
    }

    private function isAssignedMechanic(UserEloquentModel $user, OrdenTrabajoEloquentModel $order): bool
    {
        $mechanicId = MecanicoEloquentModel::query()
            ->where('usuario_id', $user->id)
            ->where('estado', 'activo')
            ->value('id');

        return $mechanicId !== null
            && $order->asignaciones()->where('activo', true)->where('mecanico_id', $mechanicId)->exists();
    }

    private function isClosed(OrdenTrabajoEloquentModel $order): bool
    {
        return in_array($order->estado, self::CLOSED_STATES, true);
    }
}
