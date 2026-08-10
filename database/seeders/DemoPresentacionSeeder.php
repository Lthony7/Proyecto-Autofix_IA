<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Auth\Infrastructure\Models\RoleEloquentModel;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Taller\Infrastructure\Models\DisponibilidadMecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class DemoPresentacionSeeder extends Seeder
{
    private const PASSWORD = 'Demo2026!Seguro';

    private array $clientes = [
        ['Carlos Andrés', 'Mendoza Vera'],
        ['María Fernanda', 'Castro Salazar'],
        ['Jorge Luis', 'Paredes Gómez'],
        ['Ana Lucía', 'Rojas Medina'],
        ['Pedro Pablo', 'Villacís Torres'],
        ['Lucía Inés', 'Suárez Delgado'],
        ['Miguel Ángel', 'Ortega Morales'],
        ['Gabriela Paz', 'Rivas Naranjo'],
        ['Fernando Xavier', 'Herrera Ibarra'],
        ['Diana Carolina', 'Mora Cárdenas'],
        ['Ricardo Andrés', 'Guerrero Pinto'],
        ['Verónica Patricia', 'Núñez Aguirre'],
        ['Santiago Emilio', 'Cordero Benavides'],
        ['Katherine Estefanía', 'Vallejo Cruz'],
        ['Alejandro David', 'Ramírez Sotomayor'],
        ['Daniela Alexandra', 'León Espinoza'],
        ['Cristian Javier', 'Moreno Andrade'],
        ['Sofía Valentina', 'Cabrera Montesdeoca'],
        ['Andrés Mauricio', 'Pacheco Riofrío'],
        ['Camila Alejandra', 'Salinas Durán'],
        ['Esteban Damián', 'Castillo Figueroa'],
        ['Natalia Isabel', 'Vargas Quintero'],
        ['Kevin Leonardo', 'Bravo Ordóñez'],
        ['Paula Andrea', 'Santillán Mera'],
        ['Marcelo Vinicio', 'Zambrano Cedeño'],
    ];

    private array $mecanicos = [
        ['Jorge', 'Guamán', 'MOTOR', true],
        ['Luis', 'Pilataxi', 'FRENOS', false],
        ['Daniel', 'Quishpe', 'ELEC', false],
        ['Roberto', 'Tituaña', 'SUSP', false],
        ['Patricio', 'Vilatuña', 'TRANS', false],
        ['Manuel', 'Chiluisa', 'CLIMA', false],
        ['Alvaro', 'Cumbal', 'MOTOR', false],
        ['Richard', 'Taco', 'FRENOS', false],
        ['Byron', 'Cachiguango', 'ELEC', false],
        ['Mario', 'Simbaña', 'SUSP', false],
        ['Wilson', 'Chimbo', 'LLAN', false],
        ['Édison', 'Casa', 'ESCP', false],
        ['Darwin', 'Guamán', 'INYE', false],
        ['Fabian', 'Yépez', 'CARP', false],
        ['Xavier', 'Albán', 'VIDR', false],
        ['Jhonny', 'Puma', 'ALAR', false],
        ['Lenin', 'Gualpa', 'ENFR', false],
        ['Cristian', 'Toapanta', 'HIBR', false],
        ['Santiago', 'Simba', 'TRANS', false],
        ['Vinicio', 'Bautista', 'CLIMA', false],
    ];

    private array $vehiculos = [
        ['Toyota', 'Corolla', ['PBA', 'PBD', 'PCU']],
        ['Chevrolet', 'Sail', ['GIA', 'GIC', 'GUE']],
        ['Kia', 'Sportage', ['PBA', 'PCD', 'PIA']],
        ['Hyundai', 'Accent', ['GCU', 'GIA', 'GSA']],
        ['Ford', 'Ranger', ['PAA', 'PCA', 'PMA']],
        ['Nissan', 'Versa', ['GGA', 'GMA', 'GTA']],
        ['Mazda', 'CX-5', ['PBA', 'PUA', 'PTA']],
        ['Suzuki', 'Swift', ['PAA', 'GAA', 'GIA']],
        ['Toyota', 'Hilux', ['PCD', 'PBD', 'PDA']],
        ['Chevrolet', 'Aveo', ['GAC', 'GBC', 'GCA']],
        ['Kia', 'Picanto', ['PIA', 'PNA', 'PSA']],
        ['Hyundai', 'Tucson', ['GPA', 'GRA', 'GSA']],
        ['Volkswagen', 'Golf', ['PMA', 'PNA', 'PTA']],
        ['Honda', 'Civic', ['GCA', 'GEA', 'GIA']],
    ];

    public function run(): void
    {
        $this->call(CatalogoTallerSeeder::class);

        $admin = UserEloquentModel::where('email', env('ADMIN_EMAIL', 'admin@autofix.local'))->first();
        $usuarioSistema = $admin ? $admin->id : UserEloquentModel::firstOrFail()->id;

        $rolCliente = RoleEloquentModel::findByName('Cliente', 'web');
        $rolMecanico = RoleEloquentModel::findByName('Mecánico', 'web');
        $rolRecepcion = RoleEloquentModel::findByName('Recepcionista', 'web');

        $this->crearRecepcionistas($rolRecepcion, $usuarioSistema);
        $this->crearMecanicos($rolMecanico, $usuarioSistema);
        $this->crearClientes($rolCliente, $usuarioSistema);

        $this->command?->info('Demo de presentación generada: 25 clientes, 20 mecánicos (1 principal), 2 recepcionistas.');
    }

    private function crearRecepcionistas(RoleEloquentModel $rol, string $usuarioSistema): void
    {
        $recepcionistas = [
            ['Melissa Fernanda', 'Chávez Luna'],
            ['Bryan Israel', 'Gordillo Paredes'],
        ];

        foreach ($recepcionistas as $i => [$nombres, $apellidos]) {
            $email = 'recepcion' . ($i + 1) . '@autofixdemo.com';
            if (UserEloquentModel::where('email', $email)->exists()) continue;

            $usuario = UserEloquentModel::create([
                'name' => "$nombres $apellidos",
                'email' => $email,
                'password' => self::PASSWORD,
                'activo' => true,
            ]);
            $usuario->syncRoles([$rol]);
        }
    }

    private function crearMecanicos(RoleEloquentModel $rol, string $usuarioSistema): void
    {
        foreach ($this->mecanicos as $i => [$nombres, $apellidos, $especialidadCodigo, $esPrincipal]) {
            $email = 'mecanico' . ($i + 1) . '@autofixdemo.com';
            if (MecanicoEloquentModel::where('email', $email)->exists()) continue;

            $usuario = UserEloquentModel::create([
                'name' => "$nombres $apellidos",
                'email' => $email,
                'password' => self::PASSWORD,
                'activo' => true,
            ]);
            $usuario->syncRoles([$rol]);

            $mecanico = MecanicoEloquentModel::create([
                'usuario_id' => $usuario->id,
                'tipo_documento' => 'CEDULA',
                'numero_documento' => $this->cedulaUnica(),
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'telefono' => '+5939' . random_int(10000000, 99999999),
                'email' => $email,
                'fecha_ingreso' => now()->subMonths(random_int(3, 40))->toDateString(),
                'estado' => 'activo',
                'creado_por' => $usuarioSistema,
                'actualizado_por' => $usuarioSistema,
            ]);

            $especialidades = $esPrincipal
                ? EspecialidadEloquentModel::where('estado', 'activo')->get()
                : EspecialidadEloquentModel::where('estado', 'activo')->where('codigo', $especialidadCodigo)->get();

            foreach ($especialidades as $especialidad) {
                DB::table('mecanico_especialidad')->updateOrInsert(
                    ['mecanico_id' => $mecanico->id, 'especialidad_id' => $especialidad->id],
                    ['activo' => true, 'asignado_en' => now(), 'asignado_por' => $usuarioSistema],
                );
            }

            foreach ([1, 2, 3, 4, 5] as $dia) {
                DisponibilidadMecanicoEloquentModel::updateOrCreate([
                    'mecanico_id' => $mecanico->id, 'dia_semana' => $dia, 'hora_inicio' => '08:00:00', 'hora_fin' => '17:00:00',
                ], ['activo' => true, 'creado_por' => $usuarioSistema]);
            }
        }
    }

    private function crearClientes(RoleEloquentModel $rol, string $usuarioSistema): void
    {
        foreach ($this->clientes as $i => [$nombres, $apellidos]) {
            $razonSocial = "$nombres $apellidos";
            $email = 'cliente' . ($i + 1) . '@autofixdemo.com';
            if (ClienteEloquentModel::where('email', $email)->exists()) continue;

            $usuario = UserEloquentModel::create([
                'name' => $razonSocial,
                'email' => $email,
                'password' => self::PASSWORD,
                'activo' => true,
            ]);
            $usuario->syncRoles([$rol]);

            $cliente = ClienteEloquentModel::create([
                'tipo_documento' => 'CEDULA',
                'numero_documento' => $this->cedulaUnica(),
                'razon_social' => $razonSocial,
                'direccion' => $this->direccion($i),
                'telefono' => '+5939' . random_int(10000000, 99999999),
                'email' => $email,
                'usuario_id' => $usuario->id,
                'estado' => 'activo',
                'creado_por' => $usuarioSistema,
                'actualizado_por' => $usuarioSistema,
            ]);

            $totalVehiculos = random_int(1, 3);
            $usadas = [];
            for ($v = 0; $v < $totalVehiculos; $v++) {
                [$marca, $modelo, $prefijos] = $this->vehiculos[array_rand($this->vehiculos)];
                $prefijo = $prefijos[array_rand($prefijos)];
                $numero = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $placa = "$prefijo-$numero";
                if (isset($usadas[$placa])) continue;
                $usadas[$placa] = true;

                VehiculoEloquentModel::create([
                    'cliente_id' => $cliente->id,
                    'placa' => $placa,
                    'placa_normalizada' => preg_replace('/[^A-Z0-9]/', '', $placa),
                    'marca' => $marca,
                    'modelo' => $modelo,
                    'anio' => random_int(2012, 2024),
                    'color' => ['Blanco', 'Negro', 'Gris', 'Plata', 'Rojo', 'Azul', 'Beige'][array_rand(['Blanco', 'Negro', 'Gris', 'Plata', 'Rojo', 'Azul', 'Beige'])],
                    'kilometraje' => random_int(1000, 120000),
                    'combustible' => ['gasolina', 'diesel', 'hibrido', 'gas', 'electrico'][random_int(0, 4)],
                    'observaciones' => null,
                    'estado' => 'activo',
                    'creado_por' => $usuarioSistema,
                    'actualizado_por' => $usuarioSistema,
                ]);
            }
        }
    }

    private function direccion(int $i): string
    {
        $ciudades = ['Quito', 'Guayaquil', 'Cuenca', 'Ambato', 'Loja', 'Machala', 'Manta', 'Ibarra'];
        $calles = ['Av. Amazonas', 'Av. 10 de Agosto', 'Av. de la Prensa', 'Av. 6 de Diciembre', 'Av. San Martín', 'Av. 9 de Octubre', 'Calle Larga', 'Av. Universitaria'];
        $ciudad = $ciudades[$i % count($ciudades)];
        $calle = $calles[$i % count($calles)];
        $numero = random_int(1, 999) . (random_int(0, 1) ? '' : ' y ' . random_int(1, 99));
        return "{$calle} {$numero}, {$ciudad}";
    }

    private function cedulaUnica(): string
    {
        do {
            $cedula = $this->generarCedula();
        } while (ClienteEloquentModel::where('numero_documento', $cedula)->exists()
            || MecanicoEloquentModel::where('numero_documento', $cedula)->exists());
        return $cedula;
    }

    private function generarCedula(): string
    {
        $provincia = str_pad((string) random_int(1, 24), 2, '0', STR_PAD_LEFT);
        $base = $provincia . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;
        foreach ($coeficientes as $indice => $coeficiente) {
            $producto = ((int) $base[$indice]) * $coeficiente;
            $suma += $producto >= 10 ? $producto - 9 : $producto;
        }
        $verificador = ((10 - ($suma % 10)) % 10);
        return $base . $verificador;
    }
}
