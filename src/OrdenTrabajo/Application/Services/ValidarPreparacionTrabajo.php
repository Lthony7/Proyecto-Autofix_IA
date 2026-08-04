<?php

namespace Src\OrdenTrabajo\Application\Services;

use Illuminate\Validation\ValidationException;
use Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\DiagnosticoTecnicoEloquentModel;

class ValidarPreparacionTrabajo
{
    public function validar(string $ordenId): void
    {
        $consultaIa = ConsultaIaEloquentModel::where('orden_id', $ordenId)->latest()->first();
        if ($consultaIa && ! in_array($consultaIa->estado, ['confirmada', 'modificada'], true)) {
            throw ValidationException::withMessages(['diagnostico' => 'El diagnóstico IA vinculado debe ser confirmado o corregido por un mecánico antes de continuar.']);
        }

        if (! DiagnosticoTecnicoEloquentModel::where('orden_id', $ordenId)->where('vigente', true)->exists()) {
            throw ValidationException::withMessages(['diagnostico' => 'Registra el diagnóstico técnico humano antes de iniciar reparaciones, completar servicios o consumir repuestos.']);
        }
    }
}
