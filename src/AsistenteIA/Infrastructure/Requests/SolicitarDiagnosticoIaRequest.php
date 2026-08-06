<?php

namespace Src\AsistenteIA\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class SolicitarDiagnosticoIaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ia.solicitar') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $campos = ['categoriaFalla', 'sintomaPrincipal', 'momentoOcurre', 'frecuencia', 'tiempoDesdeInicio', 'intensidad', 'senales', 'lucesTablero', 'perdidaPotenciaArranque', 'codigosObd', 'pruebasRealizadas', 'reparacionesRecientes', 'observaciones'];
        $datos = [
            'cliente_id' => $this->input('clienteId'), 'vehiculo_id' => $this->input('vehiculoId'),
            'puede_circular' => $this->input('puedeCircular'), 'urgencia_percibida' => $this->input('urgenciaPercibida'),
            'condiciones' => array_values(array_filter((array) $this->input('condiciones', []))),
        ];
        foreach ($campos as $campo) {
            $datos[Str::snake($campo)] = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $this->input($campo)));
        }
        $this->merge($datos);
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'uuid', Rule::exists('clientes', 'id')->where('estado', 'activo')],
            'vehiculo_id' => ['required', 'uuid', Rule::exists('vehiculos', 'id')->where('estado', 'activo')],
            'kilometraje' => ['required', 'integer', 'min:0', 'max:9999999'],
            'categoria_falla' => ['required', Rule::in(['frenos', 'motor', 'electrico', 'suspension', 'transmision', 'climatizacion', 'otro'])],
            'sintoma_principal' => ['required', 'string'],
            'momento_ocurre' => ['required', 'string'],
            'frecuencia' => ['required', Rule::in(['primera_vez', 'ocasional', 'intermitente', 'frecuente', 'permanente'])],
            'tiempo_desde_inicio' => ['required', 'string'],
            'intensidad' => ['required', Rule::in(['leve', 'moderada', 'severa'])],
            'condiciones' => ['array', 'max:8'],
            'condiciones.*' => [Rule::in(['frio', 'caliente', 'detenido', 'movimiento', 'acelerar', 'frenar', 'girar', 'subida', 'carretera', 'ciudad', 'lluvia'])],
            'senales' => ['nullable', 'string'], 'luces_tablero' => ['nullable', 'string'],
            'perdida_potencia_arranque' => ['nullable', 'string'], 'codigos_obd' => ['nullable', 'string', 'max:500'],
            'pruebas_realizadas' => ['nullable', 'string'],
            'puede_circular' => ['required', Rule::in(['si', 'con_dificultad', 'no'])],
            'urgencia_percibida' => ['required', Rule::in(['baja', 'media', 'alta', 'critica'])],
            'reparaciones_recientes' => ['nullable', 'string'], 'observaciones' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $vehiculo = $this->input('vehiculo_id') ? VehiculoEloquentModel::whereKey($this->input('vehiculo_id'))->first() : null;
            if ($vehiculo && $vehiculo->cliente_id !== $this->input('cliente_id')) {
                $validator->errors()->add('vehiculoId', 'El vehículo no pertenece al cliente seleccionado.');
            }
            if ($vehiculo && (int) $this->input('kilometraje') < (int) $vehiculo->kilometraje) {
                $validator->errors()->add('kilometraje', "El kilometraje no puede ser menor al último registrado ({$vehiculo->kilometraje} km).");
            }
            if ($vehiculo && $this->user()?->hasRole('Mecánico')) {
                $autorizado = $vehiculo->ordenes()->whereIn('estado', ['pendiente', 'en_diagnostico', 'en_reparacion'])->whereHas('asignaciones', fn ($q) => $q->where('activo', true)->whereHas('mecanico', fn ($m) => $m->where('usuario_id', $this->user()->id)))->exists()
                    || \Src\Cita\Infrastructure\Models\CitaEloquentModel::where('vehiculo_id', $vehiculo->id)->whereIn('estado', ['pendiente', 'confirmada', 'reprogramada'])->where('fin', '>=', now())->whereHas('mecanico', fn ($m) => $m->where('usuario_id', $this->user()->id))->exists();
                if (! $autorizado) $validator->errors()->add('vehiculoId', 'Solo puedes generar diagnósticos para vehículos asignados a tu trabajo.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'categoria_falla.in' => 'Selecciona un tipo de falla válido.',
            'frecuencia.in' => 'Selecciona una frecuencia válida.',
            'intensidad.required' => 'Selecciona la intensidad percibida.',
        ];
    }
}
