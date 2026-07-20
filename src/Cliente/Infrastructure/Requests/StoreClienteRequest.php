<?php

namespace Src\Cliente\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.crear') ?? false;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'tipo_documento' => mb_strtoupper(trim((string) $this->input('tipoDocumento'))),
            'numero_documento' => mb_strtoupper(preg_replace('/[\s.-]+/', '', (string) $this->input('numeroDocumento'))),
            'razon_social' => trim((string) $this->input('razonSocial')),
            'direccion' => trim((string) $this->input('direccion')),
            'telefono' => trim((string) $this->input('telefono')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(['DNI', 'RUC', 'CE', 'PASAPORTE'])],
            'numero_documento' => ['required', 'string', 'max:30', Rule::unique('clientes', 'numero_documento')],
            'razon_social' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:30',
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('clientes', 'email')],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_documento' => 'tipo de documento',
            'numero_documento' => 'número de documento',
            'razon_social' => 'razón social',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'email' => 'email',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_documento.required' => 'El tipo de documento es obligatorio',
            'tipo_documento.in' => 'El tipo de documento debe ser DNI, RUC, CE o PASAPORTE',
            'numero_documento.required' => 'El número de documento es obligatorio',
            'numero_documento.unique' => 'Este número de documento ya está registrado',
            'razon_social.required' => 'La razón social es obligatoria',
            'direccion.required' => 'La dirección es obligatoria',
            'telefono.required' => 'El teléfono es obligatorio',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'Este email ya está registrado'
        ];
    }
}
