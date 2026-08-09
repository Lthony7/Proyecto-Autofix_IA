<?php

namespace Tests\Unit\AsistenteIA;

use Illuminate\Support\Facades\Validator;
use Src\AsistenteIA\Infrastructure\Requests\SolicitarDiagnosticoIaRequest;
use Tests\TestCase;

class SolicitarDiagnosticoIaRequestTest extends TestCase
{
    public function test_limita_el_reporte_que_se_envia_al_proveedor(): void
    {
        $request = new SolicitarDiagnosticoIaRequest();
        $rules = $request->rules();

        $valido = Validator::make(['sintoma_principal' => str_repeat('a', 3000)], [
            'sintoma_principal' => $rules['sintoma_principal'],
        ]);
        $excesivo = Validator::make(['sintoma_principal' => str_repeat('a', 3001)], [
            'sintoma_principal' => $rules['sintoma_principal'],
        ]);

        $this->assertFalse($valido->fails());
        $this->assertTrue($excesivo->fails());
    }
}
