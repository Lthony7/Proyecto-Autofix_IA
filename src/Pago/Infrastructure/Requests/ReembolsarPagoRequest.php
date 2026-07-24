<?php

namespace Src\Pago\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReembolsarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pagos.reembolsar') ?? false;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:10', 'max:1000']];
    }
}
