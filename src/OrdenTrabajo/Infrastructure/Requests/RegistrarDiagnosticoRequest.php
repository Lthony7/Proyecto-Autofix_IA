<?php
namespace Src\OrdenTrabajo\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\OrdenTrabajo\Infrastructure\Models\DiagnosticoTecnicoEloquentModel;
class RegistrarDiagnosticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $orden = $this->route('orden');
        if (in_array($orden?->estado, ['finalizada', 'lista_entrega', 'entregada'], true)) return $this->user()?->can('correctDiagnosis', $orden) ?? false;
        if ($orden?->estado === 'cancelada' || ! ($this->user()?->can('technicalWork', $orden) ?? false)) return false;
        $existe = $orden && DiagnosticoTecnicoEloquentModel::where('orden_id', $orden->id)->exists();
        return $this->user()?->can($existe ? 'diagnosticos.editar' : 'diagnosticos.crear') ?? false;
    }
    public function rules(): array
    {
        $cerrada = in_array($this->route('orden')?->estado, ['finalizada', 'lista_entrega', 'entregada', 'cancelada'], true);
        return [
            'estado' => ['required', Rule::in(['borrador', 'confirmado'])],
            'diagnostico' => ['required', 'string'], 'causa' => ['nullable', 'string'],
            'componentesAfectados' => ['nullable', 'string'], 'severidad' => ['required', Rule::in(['baja', 'media', 'alta', 'critica'])],
            'pruebasRealizadas' => ['nullable', 'string'], 'recomendaciones' => ['nullable', 'string'],
            'observacionesTecnicas' => ['nullable', 'string'], 'indicacionesSeguridad' => ['nullable', 'string'],
            'puedeCircular' => ['required', Rule::in(['si', 'con_precaucion', 'no'])],
            'proximoMantenimientoEn' => ['nullable', 'date', 'after_or_equal:today'],
            'resumenCliente' => [$this->input('estado') === 'confirmado' ? 'required' : 'nullable', 'string'],
            'notasInternas' => ['nullable', 'string'],
            'motivoCorreccion' => [$cerrada ? 'required' : 'nullable', 'string'],
        ];
    }
}
