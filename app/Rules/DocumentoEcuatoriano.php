<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DocumentoEcuatoriano implements ValidationRule
{
    public function __construct(private readonly string $tipo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $documento = (string) $value;
        $tipo = mb_strtoupper($this->tipo);

        if ($tipo === 'CEDULA') {
            if (! $this->cedulaValida($documento)) {
                $fail('La cédula debe contener 10 dígitos válidos (provincia y dígito verificador correctos).');
            }
            return;
        }

        if ($tipo === 'RUC') {
            if (! $this->rucValido($documento)) {
                $fail('El RUC debe contener 13 dígitos válidos.');
            }
            return;
        }

        if ($tipo === 'PASAPORTE' && ! preg_match('/^[A-Z0-9]{5,20}$/', $documento)) {
            $fail('El pasaporte debe contener entre 5 y 20 letras o números, sin espacios ni símbolos.');
        }
    }

    private function cedulaValida(string $cedula): bool
    {
        if (! preg_match('/^\d{10}$/', $cedula)) return false;

        $provincia = (int) substr($cedula, 0, 2);
        if ($provincia < 1 || ($provincia > 24 && $provincia !== 30)) return false;

        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;
        foreach ($coeficientes as $indice => $coeficiente) {
            $producto = ((int) $cedula[$indice]) * $coeficiente;
            $suma += $producto >= 10 ? $producto - 9 : $producto;
        }

        $verificador = ((10 - ($suma % 10)) % 10);

        return $verificador === (int) $cedula[9];
    }

    private function rucValido(string $ruc): bool
    {
        if (! preg_match('/^\d{13}$/', $ruc)) return false;

        $tercerDigito = (int) $ruc[2];

        // Persona natural: los 10 primeros dígitos forman una cédula válida.
        if ($tercerDigito >= 0 && $tercerDigito <= 5) {
            return $this->cedulaValida(substr($ruc, 0, 10));
        }

        // Persona jurídica pública: módulo 11 con los 8 primeros dígitos.
        if ($tercerDigito === 6) {
            return $this->verificarModulo11(substr($ruc, 0, 8), substr($ruc, 8, 1), [3, 2, 7, 6, 5, 4, 3, 2]);
        }

        // Persona jurídica privada: módulo 11 con los 9 primeros dígitos.
        if ($tercerDigito === 9) {
            return $this->verificarModulo11(substr($ruc, 0, 9), substr($ruc, 9, 1), [4, 3, 2, 7, 6, 5, 4, 3, 2]);
        }

        return false;
    }

    private function verificarModulo11(string $base, string $digitoVerificador, array $pesos): bool
    {
        $suma = 0;
        foreach ($pesos as $indice => $peso) $suma += (int) $base[$indice] * $peso;

        $residuo = $suma % 11;
        $verificacion = 11 - $residuo;
        if ($verificacion === 11) $verificacion = 0;
        if ($verificacion === 10) $verificacion = 0;

        return $verificacion === (int) $digitoVerificador;
    }
}