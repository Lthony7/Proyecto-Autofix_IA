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
            ['MOTOR', 'Motor', 'Diagnóstico, mantenimiento y reparación de motores', [['MOT-DIAG', 'Diagnóstico de motor', 90, '45.00'], ['MOT-ACE', 'Cambio de aceite y filtro', 45, '25.00']]],
            ['FRENOS', 'Frenos', 'Inspección y reparación del sistema de frenos', [['FRE-REV', 'Revisión del sistema de frenos', 60, '30.00']]],
            ['ELEC', 'Sistema eléctrico', 'Batería, alternador, cableado y diagnóstico electrónico', [['ELE-DIAG', 'Diagnóstico eléctrico y escáner', 75, '40.00']]],
            ['SUSP', 'Suspensión y dirección', 'Amortiguadores, bujes, dirección y alineación', [['SUS-REV', 'Revisión de suspensión y dirección', 60, '30.00'], ['SUS-ALI', 'Alineación y balanceo', 60, '35.00']]],
            ['TRANS', 'Transmisión', 'Diagnóstico y mantenimiento de transmisión', [['TRA-DIAG', 'Diagnóstico de transmisión', 90, '50.00']]],
            ['CLIMA', 'Aire acondicionado', 'Diagnóstico y mantenimiento del sistema de climatización', [['CLI-REV', 'Revisión de aire acondicionado', 60, '35.00']]],
            ['LLAN', 'Llantas', 'Cambio, balanceo y rotación de llantas', [['LLA-CAM', 'Cambio de llantas', 45, '25.00'], ['LLA-ROT', 'Rotación y balanceo', 40, '22.00']]],
            ['ESCP', 'Sistema de escape', 'Mantenimiento y reparación del sistema de escape', [['ESC-REV', 'Revisión de escape', 60, '28.00']]],
            ['INYE', 'Inyección electrónica', 'Diagnóstico de inyectores, bomba y sistema de combustible', [['INY-DIAG', 'Diagnóstico de inyección', 90, '45.00'], ['INY-LIM', 'Limpieza de inyectores', 120, '60.00']]],
            ['CARP', 'Carrocería y pintura', 'Reparación de carrocería, latonería y pintura', [['CAR-LAT', 'Latonería y pintura', 240, '120.00']]],
            ['VIDR', 'Vidrios y parabrisas', 'Cambio y sellado de vidrios y parabrisas', [['VID-CAM', 'Cambio de parabrisas', 120, '70.00']]],
            ['ALAR', 'Alarmas y accesorios', 'Instalación y reparación de alarmas y accesorios', [['ALA-INS', 'Instalación de alarma', 60, '40.00']]],
            ['ENFR', 'Sistema de enfriamiento', 'Radiador, termostato y refrigerante', [['ENF-REV', 'Revisión del sistema de enfriamiento', 60, '30.00'], ['ENF-REF', 'Cambio de refrigerante', 45, '25.00']]],
            ['HIBR', 'Híbridos y eléctricos', 'Diagnóstico de vehículos híbridos y eléctricos', [['HIB-DIAG', 'Diagnóstico híbrido/eléctrico', 120, '70.00']]],
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
