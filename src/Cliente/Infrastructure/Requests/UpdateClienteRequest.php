<?php

namespace Src\Cliente\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.editar') ?? false;
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
        // Obtener el ID desde la ruta (puede ser 'id' o 'cliente' dependiendo de si es web o API)
        $clienteId = $this->route('id') ?? $this->route('cliente');

        return [
            'tipo_documento' => ['required', Rule::in(['DNI', 'RUC', 'CE', 'PASAPORTE'])],
            'numero_documento' => ['required', 'string', 'max:30', Rule::unique('clientes', 'numero_documento')->ignore($clienteId)],
            'razon_social' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:30',
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('clientes', 'email')->ignore($clienteId)],
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
            'tipo_documento.in' => 'El tipo de documento debe ser DNI, RUC, CE o PASAPORTE',
            'numero_documento.unique' => 'Este número de documento ya está registrado',
            'razon_social.required' => 'La razón social es obligatoria',
            'direccion.required' => 'La dirección es obligatoria',
            'telefono.required' => 'El teléfono es obligatorio',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'Este email ya está registrado'
        ];
    }
}
