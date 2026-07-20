<?php

namespace Src\Taller\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarServicioRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('servicios.gestionar') ?? false; }
    protected function prepareForValidation(): void { $this->merge(['especialidad_id' => $this->input('especialidadId') ?: null, 'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))), 'duracion_minutos' => $this->input('duracionMinutos'), 'precio_base' => $this->input('precioBase')]); }
    public function rules(): array
    {
        return [
            'especialidad_id' => ['nullable', 'uuid', Rule::exists('especialidades', 'id')->where('estado', 'activo')],
            'codigo' => ['required', 'max:30', Rule::unique('servicios_taller')], 'nombre' => ['required', 'max:150', Rule::unique('servicios_taller')],
            'descripcion' => ['nullable', 'string', 'max:2000'], 'duracion_minutos' => ['required', 'integer', 'between:15,1440'], 'precio_base' => ['required', 'decimal:0,2', 'min:0'],
        ];
    }
}
