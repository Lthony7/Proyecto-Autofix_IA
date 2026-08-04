<?php

namespace Tests\Unit\Validation;

use App\Rules\DocumentoColombiano;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentoColombianoTest extends TestCase
{
    #[DataProvider('documentosValidos')]
    public function test_acepta_documentos_colombianos_validos(string $tipo, string $numero): void
    {
        $errores = [];
        (new DocumentoColombiano($tipo))->validate('numero_documento', $numero, function (string $mensaje) use (&$errores) { $errores[] = $mensaje; });

        $this->assertSame([], $errores);
    }

    #[DataProvider('documentosInvalidos')]
    public function test_rechaza_documentos_con_formato_invalido(string $tipo, string $numero): void
    {
        $errores = [];
        (new DocumentoColombiano($tipo))->validate('numero_documento', $numero, function (string $mensaje) use (&$errores) { $errores[] = $mensaje; });

        $this->assertNotEmpty($errores);
    }

    public static function documentosValidos(): array
    {
        return [
            ['CC', '123456'],
            ['CC', '1234567890'],
            ['CE', '987654321'],
            ['PASAPORTE', 'AB12345'],
            ['NIT', '9001234563'],
        ];
    }

    public static function documentosInvalidos(): array
    {
        return [
            ['CC', '12345'],
            ['CC', '12345678901'],
            ['CE', 'ABC123'],
            ['PASAPORTE', 'A-12'],
            ['NIT', '9001234568'],
        ];
    }
}
