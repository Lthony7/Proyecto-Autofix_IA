<?php

namespace Tests\Unit\AsistenteIA;

use Illuminate\Support\Facades\Http;
use Src\AsistenteIA\Application\Services\GeneradorDiagnosticoInicial;
use Tests\TestCase;

class GeneradorDiagnosticoInicialTest extends TestCase
{
    public function test_modo_simulado_respeta_el_contrato_y_bloquea_circulacion_insegura(): void
    {
        config(['services.groq.enabled' => false]);

        $resultado = app(GeneradorDiagnosticoInicial::class)->generar([
            'categoria_falla' => 'frenos',
            'sintoma_principal' => 'El pedal pierde presión y el vehículo no frena correctamente.',
            'puede_circular' => 'no',
            'urgencia_percibida' => 'critica',
        ]);

        $respuesta = $resultado['respuesta'];
        $this->assertTrue($resultado['meta']['simulada']);
        $this->assertSame('diagnostico.v2', $resultado['meta']['esquema_version']);
        $this->assertSame('no', $respuesta['puede_circular']);
        $this->assertSame('muy_urgente', $respuesta['nivel_urgencia']);
        $this->assertSame('alto', $respuesta['nivel_riesgo']);
        $this->assertNotEmpty($respuesta['posibles_causas']);
        $this->assertSame(GeneradorDiagnosticoInicial::ADVERTENCIA, $respuesta['advertencia']);
    }

    public function test_respuesta_invalida_del_proveedor_usa_fallback_controlado(): void
    {
        config([
            'services.groq.enabled' => true,
            'services.groq.key' => 'test-key',
            'services.groq.model' => 'test-model',
            'services.groq.url' => 'https://groq.test',
            'services.groq.timeout' => 2,
        ]);
        Http::fake(['groq.test/*' => Http::response(['choices' => [['message' => ['content' => '{}']]]], 200)]);

        $resultado = app(GeneradorDiagnosticoInicial::class)->generar([
            'categoria_falla' => 'motor',
            'sintoma_principal' => 'El motor pierde potencia al acelerar.',
            'puede_circular' => 'con_dificultad',
            'urgencia_percibida' => 'alta',
        ]);

        $this->assertTrue($resultado['meta']['simulada']);
        $this->assertSame('fallback_proveedor', $resultado['meta']['resultado']);
        $this->assertSame('groq', $resultado['meta']['proveedor_intentado']);
        $this->assertSame('con_precaucion', $resultado['respuesta']['puede_circular']);
    }
}
