<?php

namespace Tests\Unit\Pago;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Src\Pago\Infrastructure\Requests\RegistrarPagoRequest;
use Tests\TestCase;

class RegistrarPagoRequestTest extends TestCase
{
    public function test_exige_idempotencia_y_referencia_para_medios_electronicos(): void
    {
        $sinClave = $this->validator(['monto' => '100.00', 'metodo' => 'efectivo', 'pagado_en' => now()]);
        $sinReferencia = $this->validator(['idempotencia_clave' => (string) Str::uuid(), 'monto' => '100.00', 'metodo' => 'transferencia', 'pagado_en' => now()]);

        $this->assertArrayHasKey('idempotencia_clave', $sinClave->errors()->toArray());
        $this->assertArrayHasKey('referencia', $sinReferencia->errors()->toArray());
    }

    public function test_rechaza_fechas_fuera_de_la_ventana_contable(): void
    {
        config(['autofix.payment_backdate_days' => 7]);
        $validator = $this->validator([
            'idempotencia_clave' => (string) Str::uuid(), 'monto' => '100.00', 'metodo' => 'efectivo',
            'pagado_en' => now()->subDays(8),
        ]);

        $this->assertArrayHasKey('pagadoEn', $validator->errors()->toArray());
    }

    private function validator(array $datos): \Illuminate\Contracts\Validation\Validator
    {
        $request = RegistrarPagoRequest::create('/', 'POST', $datos);
        $validator = Validator::make($datos, $request->rules());
        $validator->after($request->after());

        return $validator;
    }
}
