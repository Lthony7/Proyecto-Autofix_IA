<?php

namespace Src\OrdenTrabajo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarServicioOrdenRequest extends FormRequest
{
    public function authorize(): bool { return ($this->user()?->can('servicios.registrar') ?? false) && ($this->user()?->can('technicalWork', $this->route('orden')) ?? false); }
    public function rules(): array { return ['servicioId' => ['required', 'uuid', Rule::exists('servicios_taller', 'id')->where('estado', 'activo')], 'motivo' => ['required', 'string']]; }
}
