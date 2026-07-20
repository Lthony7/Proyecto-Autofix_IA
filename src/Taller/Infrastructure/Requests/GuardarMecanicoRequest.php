<?php

namespace Src\Taller\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarMecanicoRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('mecanicos.gestionar') ?? false; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'usuario_id' => $this->input('usuarioId') ?: null,
            'tipo_documento' => mb_strtoupper(trim((string) $this->input('tipoDocumento'))),
            'numero_documento' => mb_strtoupper(preg_replace('/[\s.-]+/', '', (string) $this->input('numeroDocumento'))),
            'nombres' => trim((string) $this->input('nombres')),
            'apellidos' => trim((string) $this->input('apellidos')),
            'telefono' => trim((string) $this->input('telefono')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'fecha_ingreso' => $this->input('fechaIngreso') ?: null,
            'especialidad_ids' => $this->input('especialidadIds', []),
            'horarios' => $this->input('horarios', []),
        ]);
    }

    public function rules(): array
    {
        $mecanico = $this->route('mecanico');
        $id = is_object($mecanico) ? $mecanico->getKey() : $mecanico;
        return [
            'usuario_id' => ['nullable', 'uuid', Rule::exists('users', 'id'), Rule::unique('mecanicos', 'usuario_id')->ignore($id)],
            'tipo_documento' => ['required', Rule::in(['CC', 'CE', 'PASAPORTE'])],
            'numero_documento' => ['required', 'max:30', Rule::unique('mecanicos')->ignore($id)],
            'nombres' => ['required', 'string', 'max:120'], 'apellidos' => ['required', 'string', 'max:120'],
            'telefono' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('mecanicos')->ignore($id)],
            'fecha_ingreso' => ['nullable', 'date', 'before_or_equal:today'],
            'especialidad_ids' => ['required', 'array', 'min:1'],
            'especialidad_ids.*' => ['uuid', Rule::exists('especialidades', 'id')->where('estado', 'activo'), 'distinct'],
            'horarios' => ['required', 'array', 'min:1'],
            'horarios.*.diaSemana' => ['required', 'integer', 'between:1,7'],
            'horarios.*.horaInicio' => ['required', 'date_format:H:i'],
            'horarios.*.horaFin' => ['required', 'date_format:H:i', 'after:horarios.*.horaInicio'],
        ];
    }
}
