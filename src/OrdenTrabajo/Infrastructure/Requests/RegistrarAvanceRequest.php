<?php

namespace Src\OrdenTrabajo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarAvanceRequest extends FormRequest
{
    public function authorize(): bool { return ($this->user()?->can('avances.registrar') ?? false) && ($this->user()?->can('technicalWork', $this->route('orden')) ?? false); }
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['avance', 'inspeccion', 'hallazgo', 'sintoma', 'prueba', 'pausa', 'recomendacion'])],
            'descripcion' => ['required', 'string'], 'visibilidad' => ['required', Rule::in(['cliente', 'interno'])],
            'servicioId' => ['nullable', 'uuid'], 'porcentaje' => ['nullable', 'integer', 'between:0,100'],
            'fechaEstimadaFinalizacion' => ['nullable', 'date', 'after:now'], 'notaInterna' => ['nullable', 'string'],
        ];
    }
}
