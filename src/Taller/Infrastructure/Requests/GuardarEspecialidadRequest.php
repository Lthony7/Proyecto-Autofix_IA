<?php

namespace Src\Taller\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarEspecialidadRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('especialidades.gestionar') ?? false; }
    protected function prepareForValidation(): void { $this->merge(['codigo' => mb_strtoupper(trim((string) $this->input('codigo'))), 'nombre' => trim((string) $this->input('nombre'))]); }
    public function rules(): array
    {
        return ['codigo' => ['required', 'max:30', Rule::unique('especialidades')], 'nombre' => ['required', 'max:120', Rule::unique('especialidades')], 'descripcion' => ['nullable', 'string']];
    }
}
