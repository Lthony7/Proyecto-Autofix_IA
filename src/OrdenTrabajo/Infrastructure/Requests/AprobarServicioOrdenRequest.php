<?php

namespace Src\OrdenTrabajo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AprobarServicioOrdenRequest extends FormRequest
{
    public function authorize(): bool { return ($this->user()?->can('servicios.aprobar') ?? false) && ($this->user()?->can('mutate', $this->route('orden')) ?? false); }
    public function rules(): array { return ['estado' => ['required', Rule::in(['aprobado', 'rechazado'])], 'motivo' => ['required', 'string']]; }
}
