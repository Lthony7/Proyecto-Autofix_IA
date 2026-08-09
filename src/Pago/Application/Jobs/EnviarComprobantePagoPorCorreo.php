<?php

namespace Src\Pago\Application\Jobs;

use App\Mail\ComprobantePagoMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;
use Src\Pago\Application\Services\GenerarComprobantePagoPdf;
use Src\Pago\Infrastructure\Models\PagoEloquentModel;

class EnviarComprobantePagoPorCorreo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $pagoId, public readonly string $usuarioId) {}

    public function handle(GenerarComprobantePagoPdf $generador, RegistrarAuditoria $auditoria): void
    {
        $pago = PagoEloquentModel::findOrFail($this->pagoId);
        $factura = FacturaOrdenEloquentModel::findOrFail($pago->factura_id);
        $correo = filter_var($factura->cliente_email, FILTER_VALIDATE_EMAIL);
        if ($correo === false) {
            throw new \RuntimeException('La factura asociada no tiene un correo de cliente válido en su instantánea.');
        }

        $datos = $generador->datos($pago);
        Mail::to($correo)->send(new ComprobantePagoMail($datos, $generador->generar($pago)));
        $auditoria->registrar('pago.comprobante_correo_enviado', 'pago', $pago->id, ['destinatario' => $correo], null, $this->usuarioId);
    }
}
