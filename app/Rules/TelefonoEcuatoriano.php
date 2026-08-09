<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TelefonoEcuatoriano implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^(?:\+5939\d{8}|\+593[2-7]\d{7}|09\d{8}|0[2-7]\d{7})$/', (string) $value)) {
            $fail('Ingresa un teléfono ecuatoriano válido, por ejemplo 0987654321, o usa el prefijo +593.');
        }
    }
}