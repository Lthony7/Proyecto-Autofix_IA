<?php

namespace Src\Taller\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoCatalogoRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->canAny(['mecanicos.gestionar', 'especialidades.gestionar', 'servicios.gestionar']) ?? false; }
    public function rules(): array { return ['estado' => ['required', Rule::in(['activo', 'inactivo', 'archivado'])]]; }
}
