<?php

namespace Src\OrdenTrabajo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoRepuestoRequeridoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $administrative = in_array($this->input('estado'), ['aprobado', 'no_disponible'], true);
        $permission = $administrative ? 'repuestos.aprobar' : 'repuestos.solicitar';
        $ability = $administrative ? 'mutate' : 'technicalWork';

        return ($this->user()?->can($permission) ?? false)
            && ($this->user()?->can($ability, $this->route('orden')) ?? false);
    }
    public function rules(): array
    {
        return ['estado' => ['required', Rule::in(['aprobado', 'no_disponible', 'no_utilizado', 'cancelado'])], 'motivo' => ['required', 'string'], 'precioUnitarioAprobado' => ['nullable', 'decimal:0,2', 'min:0']];
    }
}
