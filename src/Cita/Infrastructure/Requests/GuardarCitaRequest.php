<?php

namespace Src\Cita\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class GuardarCitaRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('citas.crear') ?? false; }
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cliente_id' => $this->input('clienteId'), 'vehiculo_id' => $this->input('vehiculoId'),
            'especialidad_id' => $this->input('especialidadId') ?: null, 'servicio_id' => $this->input('servicioId') ?: null,
            'mecanico_id' => $this->input('mecanicoId') ?: null,
            'inicio' => trim((string) $this->input('fecha')).' '.trim((string) $this->input('horaInicio')),
            'motivo' => trim((string) $this->input('motivo')),
            'consulta_ia_id' => $this->input('consultaIaId') ?: null,
        ]);
    }
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'uuid', Rule::exists('clientes', 'id')->where('estado', 'activo')],
            'vehiculo_id' => ['required', 'uuid', Rule::exists('vehiculos', 'id')->where('estado', 'activo')],
            'especialidad_id' => ['nullable', 'required_without:servicio_id', 'uuid', Rule::exists('especialidades', 'id')->where('estado', 'activo')],
            'servicio_id' => ['nullable', 'uuid', Rule::exists('servicios_taller', 'id')->where('estado', 'activo')],
            'mecanico_id' => ['nullable', 'uuid', Rule::exists('mecanicos', 'id')->where('estado', 'activo')],
            'motivo' => ['required', 'string', 'min:10', 'max:3000'], 'kilometraje' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'inicio' => ['required', 'date', 'after:now'],
            'consulta_ia_id' => ['nullable', 'uuid', Rule::exists('consultas_ia', 'id')],
        ];
    }
    public function after(): array
    {
        return [function ($validator) {
            $clienteId = $this->input('cliente_id'); $vehiculoId = $this->input('vehiculo_id');
            if ($clienteId && $vehiculoId && ! VehiculoEloquentModel::whereKey($vehiculoId)->where('cliente_id', $clienteId)->exists()) $validator->errors()->add('vehiculoId', 'El vehículo no pertenece al cliente seleccionado.');
            if ($this->user()?->hasRole('Cliente') && ! ClienteEloquentModel::whereKey($clienteId)->where('usuario_id', $this->user()->id)->exists()) $validator->errors()->add('clienteId', 'Solo puedes agendar citas para tu propia cuenta.');
            if ($this->input('consulta_ia_id') && ! \Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel::whereKey($this->input('consulta_ia_id'))->where('cliente_id', $clienteId)->where('vehiculo_id', $vehiculoId)->whereNull('cita_id')->exists()) $validator->errors()->add('consultaIaId', 'La consulta IA no está disponible para esta cita.');
        }];
    }
}
