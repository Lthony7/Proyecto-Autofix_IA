<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;

class CatalogoTallerSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = [
            ['MOTOR', 'Motor', 'Diagnóstico, mantenimiento y reparación de motores', [['MOT-DIAG', 'Diagnóstico de motor', 90, '120000.00'], ['MOT-ACE', 'Cambio de aceite y filtro', 45, '80000.00']]],
            ['FRENOS', 'Frenos', 'Inspección y reparación del sistema de frenos', [['FRE-REV', 'Revisión del sistema de frenos', 60, '90000.00']]],
            ['ELEC', 'Sistema eléctrico', 'Batería, alternador, cableado y diagnóstico electrónico', [['ELE-DIAG', 'Diagnóstico eléctrico y escáner', 75, '110000.00']]],
            ['SUSP', 'Suspensión y dirección', 'Amortiguadores, bujes, dirección y alineación', [['SUS-REV', 'Revisión de suspensión y dirección', 60, '90000.00'], ['SUS-ALI', 'Alineación y balanceo', 60, '100000.00']]],
            ['TRANS', 'Transmisión', 'Diagnóstico y mantenimiento de transmisión', [['TRA-DIAG', 'Diagnóstico de transmisión', 90, '130000.00']]],
            ['CLIMA', 'Aire acondicionado', 'Diagnóstico y mantenimiento del sistema de climatización', [['CLI-REV', 'Revisión de aire acondicionado', 60, '95000.00']]],
        ];

        foreach ($catalogo as [$codigo, $nombre, $descripcion, $servicios]) {
            $especialidad = EspecialidadEloquentModel::where('codigo', $codigo)->first();
            $datosEspecialidad = ['nombre' => $nombre, 'descripcion' => $descripcion, 'estado' => 'activo'];
            if ($especialidad) {
                $especialidad->update($datosEspecialidad);
            } else {
                $especialidad = EspecialidadEloquentModel::create(['codigo' => $codigo, ...$datosEspecialidad]);
            }
            foreach ($servicios as [$codigoServicio, $nombreServicio, $duracion, $precio]) {
                $servicio = ServicioEloquentModel::where('codigo', $codigoServicio)->first();
                $datosServicio = ['especialidad_id' => $especialidad->id, 'nombre' => $nombreServicio, 'duracion_minutos' => $duracion, 'precio_base' => $precio, 'estado' => 'activo'];
                $servicio ? $servicio->update($datosServicio) : ServicioEloquentModel::create(['codigo' => $codigoServicio, ...$datosServicio]);
            }
        }
    }
}
