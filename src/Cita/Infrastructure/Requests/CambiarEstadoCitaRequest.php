<?php

namespace Src\Cita\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permiso = $this->input('estado') === 'cancelada' ? 'citas.cancelar' : 'citas.gestionar';
        return $this->user()?->can($permiso) ?? false;
    }
    protected function prepareForValidation(): void
    {
        if ($this->input('fecha') && $this->input('horaInicio')) $this->merge(['inicio' => $this->input('fecha').' '.$this->input('horaInicio')]);
        $this->merge(['mecanico_id' => $this->input('mecanicoId') ?: null]);
    }
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['confirmada', 'reprogramada', 'atendida', 'cancelada'])],
            'observaciones' => ['nullable', 'string'],
            'motivo' => ['required_if:estado,cancelada', 'nullable', 'string'],
            'inicio' => ['required_if:estado,reprogramada', 'nullable', 'date', 'after:now'],
            'mecanico_id' => ['nullable', 'uuid', Rule::exists('mecanicos', 'id')->where('estado', 'activo')],
        ];
    }
}
