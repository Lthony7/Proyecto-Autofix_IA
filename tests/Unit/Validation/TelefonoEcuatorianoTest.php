<?php

namespace Tests\Unit\Validation;

use App\Rules\TelefonoEcuatoriano;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelefonoEcuatorianoTest extends TestCase
{
    #[DataProvider('telefonos')]
    public function test_valida_telefonos_ecuatorianos(string $telefono, bool $valido): void
    {
        $errores = [];
        (new TelefonoEcuatoriano)->validate('telefono', $telefono, function (string $mensaje) use (&$errores) { $errores[] = $mensaje; });

        $this->assertSame($valido, $errores === []);
    }

    public static function telefonos(): array
    {
        return [
            ['0987654321', true],
            ['+593987654321', true],
            ['022222222', true],
            ['+59322345678', true],
            ['3001234567', false],
            ['+573001234567', false],
            ['098765432', false],
            ['telefono', false],
        ];
    }
}