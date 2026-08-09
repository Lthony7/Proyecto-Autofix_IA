<?php
namespace Src\OrdenTrabajo\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\OrdenTrabajo\Application\Services\FlujoEstadosOrden;
class CambiarEstadoOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = match ($this->input('estado')) { 'asignada' => 'assign', 'cancelada' => 'cancel', 'lista_entrega', 'entregada' => 'deliver', default => 'technicalWork' };
        $permission = match ($this->input('estado')) { 'asignada' => 'ordenes.asignar', 'cancelada' => 'ordenes.cancelar', 'lista_entrega', 'entregada' => 'ordenes.entregar', default => 'ordenes.actualizar_estado' };

        return ($this->user()?->can($permission) ?? false)
            && ($this->user()?->can($ability, $this->route('orden')) ?? false);
    }
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(FlujoEstadosOrden::DESTINOS)],
            'observaciones' => ['nullable', 'string'],
            'motivo' => [Rule::requiredIf(fn () => in_array($this->input('estado'), ['pausada','cancelada'], true)), 'nullable', 'string'],
            'observacionesEntrega' => [Rule::requiredIf(fn () => $this->input('estado') === 'lista_entrega'), 'nullable', 'string'],
        ];
    }
}
