<?php

namespace Src\Facturacion\Application\Jobs;

use App\Mail\FacturaDocumentoMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Facturacion\Application\Services\GenerarFacturaPdf;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;

class EnviarFacturaPorCorreo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $facturaId, public readonly string $usuarioId) {}

    public function handle(GenerarFacturaPdf $generador, RegistrarAuditoria $auditoria): void
    {
        $factura = FacturaOrdenEloquentModel::findOrFail($this->facturaId);
        $correo = filter_var($factura->cliente_email, FILTER_VALIDATE_EMAIL);
        if ($correo === false) {
            throw new \RuntimeException('La factura no tiene un correo de cliente válido en su instantánea.');
        }

        $datos = $generador->datos($factura);
        Mail::to($correo)->send(new FacturaDocumentoMail($datos, $generador->generar($factura)));
        $auditoria->registrar('factura_orden.correo_enviado', 'factura_orden', $factura->id, ['destinatario' => $correo], null, $this->usuarioId);
    }
}
