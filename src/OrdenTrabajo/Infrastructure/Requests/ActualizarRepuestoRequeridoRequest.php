<?php

namespace Src\OrdenTrabajo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarRepuestoRequeridoRequest extends FormRequest
{
    public function authorize(): bool { return ($this->user()?->can('repuestos.solicitar') ?? false) && ($this->user()?->can('technicalWork', $this->route('orden')) ?? false); }
    public function rules(): array
    {
        return ['cantidad' => ['required', 'decimal:0,3', 'gt:0', 'max:9999'], 'prioridad' => ['required', Rule::in(['baja', 'media', 'alta', 'critica'])], 'obligatorio' => ['required', 'boolean'], 'motivo' => ['required', 'string']];
    }
}
