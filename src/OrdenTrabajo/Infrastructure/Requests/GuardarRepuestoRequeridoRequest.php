<?php

namespace Src\OrdenTrabajo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarRepuestoRequeridoRequest extends FormRequest
{
    public function authorize(): bool { return ($this->user()?->can('repuestos.solicitar') ?? false) && ($this->user()?->can('technicalWork', $this->route('orden')) ?? false); }
    public function rules(): array
    {
        return [
            'repuestoId' => ['nullable', 'uuid', Rule::exists('repuestos', 'id')->where('estado', 'activo')],
            'descripcion' => ['required', 'string'], 'cantidad' => ['required', 'decimal:0,3', 'gt:0', 'max:9999'],
            'prioridad' => ['required', Rule::in(['baja', 'media', 'alta', 'critica'])], 'obligatorio' => ['required', 'boolean'],
            'fuenteSuministro' => ['required', Rule::in(['inventario', 'externo', 'cliente'])], 'unidad' => ['nullable', 'string', 'max:30'],
            'motivo' => ['required', 'string'],
        ];
    }
}
