<?php

namespace Src\Pago\Infrastructure\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;

class RegistrarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pagos.registrar') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pagado_en' => $this->input('pagadoEn', $this->input('pagado_en')),
            'idempotencia_clave' => $this->input('idempotenciaClave', $this->input('idempotencia_clave')),
        ]);
    }

    public function rules(): array
    {
        return [
            'idempotencia_clave' => ['required', 'uuid'],
            'monto' => ['required', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'metodo' => ['required', Rule::in(['efectivo', 'tarjeta', 'transferencia', 'otro'])],
            'referencia' => ['nullable', 'string', 'max:120', Rule::requiredIf(fn () => in_array($this->input('metodo'), ['tarjeta', 'transferencia'], true))],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'pagado_en' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $this->input('pagado_en')) return;
            try { $fecha = CarbonImmutable::parse($this->input('pagado_en')); } catch (\Throwable) { return; }
            if ($fecha->isBefore(now()->subDays((int) config('autofix.payment_backdate_days', 7)))) {
                $validator->errors()->add('pagadoEn', 'La fecha supera el límite permitido para registrar pagos anteriores.');
            }
            $orden = $this->route('orden');
            $factura = $orden ? FacturaOrdenEloquentModel::where('orden_id', $orden->id)->where('estado', 'emitida')->first() : null;
            if ($factura && $fecha->isBefore($factura->emitida_en)) {
                $validator->errors()->add('pagadoEn', 'La fecha del pago no puede ser anterior a la factura.');
            }
        }];
    }
}
