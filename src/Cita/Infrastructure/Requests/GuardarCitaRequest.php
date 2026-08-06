<?php

namespace Src\Cita\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;
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
            'repuestos_solicitados' => collect($this->input('repuestosSolicitados', []))->map(fn ($item) => [
                'repuesto_id' => $item['repuestoId'] ?? null,
                'descripcion' => trim((string) ($item['descripcion'] ?? '')),
                'cantidad' => $item['cantidad'] ?? null,
                'observaciones' => trim((string) ($item['observaciones'] ?? '')) ?: null,
            ])->all(),
        ]);
    }
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'uuid', Rule::exists('clientes', 'id')->where('estado', 'activo')],
            'vehiculo_id' => ['required', 'uuid', Rule::exists('vehiculos', 'id')->where('estado', 'activo')],
            'especialidad_id' => ['required', 'uuid', Rule::exists('especialidades', 'id')->where('estado', 'activo')],
            'servicio_id' => ['nullable', 'uuid', Rule::exists('servicios_taller', 'id')->where('estado', 'activo')],
            'mecanico_id' => ['required', 'uuid', Rule::exists('mecanicos', 'id')->where('estado', 'activo')],
            'motivo' => ['required', 'string'], 'kilometraje' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'inicio' => ['required', 'date', 'after:now'],
            'consulta_ia_id' => ['nullable', 'uuid', Rule::exists('consultas_ia', 'id')],
            'repuestos_solicitados' => ['array', 'max:10'],
            'repuestos_solicitados.*.repuesto_id' => ['nullable', 'uuid', Rule::exists('repuestos', 'id')->where('estado', 'activo')],
            'repuestos_solicitados.*.descripcion' => ['required', 'string'],
            'repuestos_solicitados.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'repuestos_solicitados.*.observaciones' => ['nullable', 'string'],
        ];
    }
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecciona el cliente de la cita.',
            'vehiculo_id.required' => 'Selecciona el vehículo de la cita.',
            'especialidad_id.required' => 'Selecciona la especialidad requerida.',
            'mecanico_id.required' => 'Selecciona un mecánico para consultar sus fechas y horarios disponibles.',
            'motivo.required' => 'Describe los síntomas o el motivo de la cita.',
            'inicio.required' => 'Selecciona la fecha y la hora de la cita.',
            'inicio.date' => 'La fecha y la hora de la cita no son válidas.',
            'inicio.after' => 'La cita debe programarse para una fecha y hora futuras.',
        ];
    }
    public function after(): array
    {
        return [function ($validator) {
            $clienteId = $this->input('cliente_id'); $vehiculoId = $this->input('vehiculo_id');
            if ($clienteId && $vehiculoId && ! VehiculoEloquentModel::whereKey($vehiculoId)->where('cliente_id', $clienteId)->exists()) $validator->errors()->add('vehiculoId', 'El vehículo no pertenece al cliente seleccionado.');
            if ($this->user()?->hasRole('Cliente') && ! ClienteEloquentModel::whereKey($clienteId)->where('usuario_id', $this->user()->id)->exists()) $validator->errors()->add('clienteId', 'Solo puedes agendar citas para tu propia cuenta.');
            $vehiculo = $vehiculoId ? VehiculoEloquentModel::find($vehiculoId) : null;
            if ($vehiculo && $this->filled('kilometraje') && (int) $this->input('kilometraje') < (int) $vehiculo->kilometraje) $validator->errors()->add('kilometraje', "El kilometraje no puede ser menor al último registrado ({$vehiculo->kilometraje} km).");
            if ($this->input('consulta_ia_id') && ! \Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel::whereKey($this->input('consulta_ia_id'))->visiblePara($this->user())->where('cliente_id', $clienteId)->where('vehiculo_id', $vehiculoId)->whereNull('cita_id')->whereNotIn('estado', ['descartada', 'cerrada'])->exists()) $validator->errors()->add('consultaIaId', 'La consulta IA no está disponible para esta cita.');
            $especialidadId = $this->input('especialidad_id');
            if ($this->input('servicio_id') && ! ServicioEloquentModel::whereKey($this->input('servicio_id'))->where('especialidad_id', $especialidadId)->exists()) $validator->errors()->add('servicioId', 'El servicio no pertenece a la especialidad seleccionada.');
            if ($this->input('mecanico_id') && ! MecanicoEloquentModel::whereKey($this->input('mecanico_id'))->whereHas('especialidades', fn ($q) => $q->where('especialidades.id', $especialidadId)->where('mecanico_especialidad.activo', true))->exists()) $validator->errors()->add('mecanicoId', 'El mecánico no tiene activa la especialidad seleccionada.');
        }];
    }
}
