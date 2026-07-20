<?php

namespace Src\Vehiculo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vehiculos.desactivar') ?? false;
    }

    public function rules(): array
    {
        return ['estado' => ['required', Rule::in(['activo', 'inactivo', 'archivado'])]];
    }
}
