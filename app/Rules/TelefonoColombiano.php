<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TelefonoColombiano implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^(?:\+57)?(?:3\d{9}|60[1-8]\d{7})$/', (string) $value)) {
            $fail('Ingresa un teléfono colombiano válido de 10 dígitos, por ejemplo 3001234567, o usa el prefijo +57.');
        }
    }
}
