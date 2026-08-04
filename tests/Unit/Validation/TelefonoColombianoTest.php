<?php

namespace Tests\Unit\Validation;

use App\Rules\TelefonoColombiano;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelefonoColombianoTest extends TestCase
{
    #[DataProvider('telefonos')]
    public function test_valida_telefonos_colombianos(string $telefono, bool $valido): void
    {
        $errores = [];
        (new TelefonoColombiano)->validate('telefono', $telefono, function (string $mensaje) use (&$errores) { $errores[] = $mensaje; });

        $this->assertSame($valido, $errores === []);
    }

    public static function telefonos(): array
    {
        return [
            ['3001234567', true],
            ['+573001234567', true],
            ['6012345678', true],
            ['1234567890', false],
            ['300123456', false],
            ['telefono', false],
        ];
    }
}
