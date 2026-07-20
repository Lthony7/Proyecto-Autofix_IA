<?php

namespace Src\Cliente\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.desactivar') ?? false;
    }

    public function rules(): array
    {
        return ['estado' => ['required', Rule::in(['activo', 'inactivo', 'archivado'])]];
    }
}
