<?php

namespace Src\Vehiculo\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permiso = $this->isMethod('post') ? 'vehiculos.crear' : 'vehiculos.editar';

        return $this->user()?->can($permiso) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $placa = mb_strtoupper(trim((string) $this->input('placa')));
        $this->merge([
            'cliente_id' => $this->input('clienteId'),
            'placa' => $placa,
            'placa_normalizada' => preg_replace('/[^A-Z0-9]/', '', $placa),
            'marca' => trim((string) $this->input('marca')),
            'modelo' => trim((string) $this->input('modelo')),
            'anio' => $this->input('anio'),
            'kilometraje' => $this->input('kilometraje'),
            'combustible' => mb_strtolower((string) $this->input('combustible')),
        ]);
    }

    public function rules(): array
    {
        $vehiculo = $this->route('vehiculo');
        $id = is_object($vehiculo) ? $vehiculo->getKey() : $vehiculo;

        return [
            'cliente_id' => ['required', 'uuid', Rule::exists('clientes', 'id')->where('estado', 'activo')],
            'placa' => ['required', 'string', 'max:20'],
            'placa_normalizada' => ['required', 'string', 'min:5', 'max:20', Rule::unique('vehiculos')->ignore($id)],
            'marca' => ['required', 'string', 'max:80'],
            'modelo' => ['required', 'string', 'max:100'],
            'anio' => ['required', 'integer', 'between:1900,'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'kilometraje' => ['required', 'integer', 'min:0', 'max:9999999'],
            'combustible' => ['required', Rule::in(['gasolina', 'diesel', 'gas', 'hibrido', 'electrico'])],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
