<?php

namespace Src\Facturacion\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EmitirFacturaOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('facturas.crear') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tasa_impuesto' => $this->input('tasaImpuesto', 0),
            'vence_en' => $this->input('venceEn') ?: null,
            'motivo_descuento' => $this->input('motivoDescuento') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'descuento' => ['required', 'decimal:0,2', 'min:0'],
            'motivo_descuento' => ['nullable', 'string'],
            'tasa_impuesto' => ['required', 'decimal:0,2', 'between:0,100'],
            'vence_en' => ['nullable', 'date', 'after_or_equal:today'],
            'observaciones' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ((float) $this->input('descuento', 0) > 0) {
                if (! $this->filled('motivo_descuento')) {
                    $validator->errors()->add('motivo_descuento', 'Debes indicar el motivo del descuento.');
                }
                if (! $this->user()?->can('descuentos.autorizar')) {
                    $validator->errors()->add('descuento', 'No tienes permiso para autorizar descuentos.');
                }
            }
        });
    }
}
