<?php

namespace Src\AsistenteIA\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevisarSugerenciaIaRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('ia.revisar') ?? false; }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['en_revision', 'confirmada', 'modificada', 'descartada'])],
            'coincideIa' => ['required_unless:estado,en_revision,descartada', 'nullable', 'boolean'],
            'observacionesCliente' => ['required_if:estado,confirmada,modificada', 'nullable', 'string', 'min:10', 'max:3000'],
            'notasInternas' => ['nullable', 'string', 'max:3000'],
            'motivoDiferencia' => ['required_if:estado,modificada,descartada', 'nullable', 'string', 'min:10', 'max:2000'],
            'diagnosticoCorregido' => ['required_if:estado,modificada', 'nullable', 'string', 'min:20', 'max:4000'],
            'servicioCorregido' => ['nullable', 'string', 'max:200'],
            'prioridadCorregida' => ['nullable', Rule::in(['baja', 'media', 'alta', 'critica'])],
            'pruebasRealizadas' => ['nullable', 'string', 'max:3000'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
            'resumenAjustado' => ['nullable', 'string', 'max:1800'],
        ];
    }

    public function messages(): array
    {
        return [
            'coincideIa.required_unless' => 'Indica si el diagnóstico IA coincide con tu análisis.',
            'observacionesCliente.required_if' => 'Escribe observaciones claras para el cliente.',
            'motivoDiferencia.required_if' => 'Explica el motivo de la diferencia o descarte.',
            'diagnosticoCorregido.required_if' => 'Registra el diagnóstico técnico corregido.',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $estado = $this->input('estado');
            $coincide = filter_var($this->input('coincideIa'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($estado === 'confirmada' && $coincide !== true) $validator->errors()->add('coincideIa', 'Una confirmación debe indicar que el diagnóstico coincide.');
            if ($estado === 'modificada' && $coincide !== false) $validator->errors()->add('coincideIa', 'Un diagnóstico modificado debe indicar que la IA no coincide.');
        }];
    }
}
