<?php

namespace Tests\Unit\Validation;

use App\Rules\DocumentoEcuatoriano;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentoEcuatorianoTest extends TestCase
{
    #[DataProvider('documentosValidos')]
    public function test_acepta_documentos_ecuatorianos_validos(string $tipo, string $numero): void
    {
        $errores = [];
        (new DocumentoEcuatoriano($tipo))->validate('numero_documento', $numero, function (string $mensaje) use (&$errores) { $errores[] = $mensaje; });

        $this->assertSame([], $errores);
    }

    #[DataProvider('documentosInvalidos')]
    public function test_rechaza_documentos_con_formato_invalido(string $tipo, string $numero): void
    {
        $errores = [];
        (new DocumentoEcuatoriano($tipo))->validate('numero_documento', $numero, function (string $mensaje) use (&$errores) { $errores[] = $mensaje; });

        $this->assertNotEmpty($errores);
    }

    public static function documentosValidos(): array
    {
        return [
            ['CEDULA', '1028267704'],
            ['CEDULA', '1021632250'],
            ['RUC', '1028267704001'],
            ['PASAPORTE', 'AB12345'],
        ];
    }

    public static function documentosInvalidos(): array
    {
        return [
            ['CEDULA', '12345'],
            ['CEDULA', '1028267705'],
            ['CEDULA', '1021632251'],
            ['RUC', '10282677040000'],
            ['RUC', '10216322500001'],
            ['PASAPORTE', 'A-12'],
            ['PASAPORTE', 'ab'],
        ];
    }
}