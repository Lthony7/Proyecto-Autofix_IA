<?php

namespace Src\OrdenTrabajo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarCierreTecnicoRequest extends FormRequest
{
    public function authorize(): bool { return ($this->user()?->can('ordenes.cierre_tecnico') ?? false) && ($this->user()?->can('technicalWork', $this->route('orden')) ?? false); }
    public function rules(): array
    {
        return [
            'tiempoTrabajadoMinutos' => ['required', 'integer', 'min:0', 'max:1000000'], 'bloqueosTecnicos' => ['nullable', 'string'],
            'controlCalidadEstado' => ['required', Rule::in(['pendiente', 'aprobado', 'rechazado'])], 'controlCalidadNotas' => ['nullable', 'string'],
            'pruebaRutaEstado' => ['required', Rule::in(['pendiente', 'aprobada', 'con_observaciones', 'no_aplica'])], 'pruebaRutaNotas' => ['nullable', 'string'],
            'observacionesEntrega' => ['nullable', 'string'], 'proximoMantenimientoEn' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
