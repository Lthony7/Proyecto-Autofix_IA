<?php

namespace Tests\Unit\AsistenteIA;

use Illuminate\Support\Facades\Validator;
use Src\AsistenteIA\Infrastructure\Requests\RevisarSugerenciaIaRequest;
use Tests\TestCase;

class RevisarSugerenciaIaRequestTest extends TestCase
{
    public function test_rechaza_confirmacion_que_indica_que_la_ia_no_coincide(): void
    {
        $validator = $this->validator(['estado' => 'confirmada', 'coincideIa' => false, 'observacionesCliente' => 'Se verificó físicamente el vehículo.']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('coincideIa', $validator->errors()->toArray());
    }

    public function test_rechaza_modificacion_que_indica_que_la_ia_si_coincide(): void
    {
        $validator = $this->validator([
            'estado' => 'modificada',
            'coincideIa' => true,
            'observacionesCliente' => 'Se verificó físicamente el vehículo.',
            'motivoDiferencia' => 'Las pruebas físicas muestran una causa distinta.',
            'diagnosticoCorregido' => 'El diagnóstico técnico confirmado es diferente al preliminar.',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('coincideIa', $validator->errors()->toArray());
    }

    public function test_acepta_combinaciones_coherentes(): void
    {
        $confirmada = $this->validator(['estado' => 'confirmada', 'coincideIa' => true, 'observacionesCliente' => 'Se verificó físicamente el vehículo.']);
        $modificada = $this->validator([
            'estado' => 'modificada',
            'coincideIa' => false,
            'observacionesCliente' => 'Se verificó físicamente el vehículo.',
            'motivoDiferencia' => 'Las pruebas físicas muestran una causa distinta.',
            'diagnosticoCorregido' => 'El diagnóstico técnico confirmado es diferente al preliminar.',
        ]);

        $this->assertFalse($confirmada->fails());
        $this->assertFalse($modificada->fails());
    }

    private function validator(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $request = RevisarSugerenciaIaRequest::create('/', 'POST', $data);
        $validator = Validator::make($data, $request->rules());
        $validator->after($request->after());

        return $validator;
    }
}
