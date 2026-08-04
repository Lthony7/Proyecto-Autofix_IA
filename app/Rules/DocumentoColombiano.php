<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DocumentoColombiano implements ValidationRule
{
    public function __construct(private readonly string $tipo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $documento = (string) $value;

        if (in_array($this->tipo, ['CC', 'CE'], true) && ! preg_match('/^\d{6,10}$/', $documento)) {
            $fail("El número de {$this->tipo} debe contener entre 6 y 10 dígitos.");
            return;
        }

        if ($this->tipo === 'PASAPORTE' && ! preg_match('/^[A-Z0-9]{5,20}$/', $documento)) {
            $fail('El pasaporte debe contener entre 5 y 20 letras o números, sin espacios ni símbolos.');
            return;
        }

        if ($this->tipo === 'NIT' && ! $this->nitValido($documento)) {
            $fail('El NIT debe contener 9 dígitos más un dígito de verificación válido.');
        }
    }

    private function nitValido(string $nit): bool
    {
        if (! preg_match('/^\d{10}$/', $nit)) return false;

        $pesos = [71, 67, 59, 53, 47, 43, 41, 37, 29];
        $suma = 0;
        foreach ($pesos as $indice => $peso) $suma += (int) $nit[$indice] * $peso;
        $residuo = $suma % 11;
        $verificacion = $residuo > 1 ? 11 - $residuo : $residuo;

        return $verificacion === (int) $nit[9];
    }
}
