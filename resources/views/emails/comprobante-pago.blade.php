<!doctype html>
<html lang="es"><body style="font-family:Arial,sans-serif;color:#172033;line-height:1.5">
    <h1 style="font-size:20px">Comprobante de pago {{ $documento['comprobanteNumero'] }}</h1>
    <p>Hola {{ $documento['clienteNombre'] }},</p>
    <p>Adjuntamos el comprobante de su pago por <strong>$ {{ number_format((float) $documento['monto'], 2, ',', '.') }} {{ $documento['moneda'] }}</strong>, asociado a la factura {{ $documento['facturaNumero'] }}.</p>
    <p>Este es un documento de control interno del taller y no sustituye una factura electrónica DIAN.</p>
    <p>AUTOFIX</p>
</body></html>
