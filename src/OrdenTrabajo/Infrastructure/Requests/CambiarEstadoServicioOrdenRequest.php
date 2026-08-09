<?php

namespace Src\OrdenTrabajo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoServicioOrdenRequest extends FormRequest
{
    public function authorize(): bool { return ($this->user()?->can('servicios.registrar') ?? false) && ($this->user()?->can('technicalWork', $this->route('orden')) ?? false); }
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['en_proceso', 'completado', 'cancelado'])], 'observaciones' => ['nullable', 'string'],
            'trabajoRealizado' => [$this->input('estado') === 'completado' ? 'required' : 'nullable', 'string'],
            'tiempoTrabajadoMinutos' => [$this->input('estado') === 'completado' ? 'required' : 'nullable', 'integer', 'min:1'],
            'resultadoPrueba' => ['nullable', 'string'], 'observacionesPosteriores' => ['nullable', 'string'], 'recomendacionesCliente' => ['nullable', 'string'],
        ];
    }
}
