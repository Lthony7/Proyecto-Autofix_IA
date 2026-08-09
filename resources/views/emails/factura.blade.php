<!doctype html>
<html lang="es"><body style="font-family:Arial,sans-serif;color:#172033;line-height:1.5">
    <h1 style="font-size:20px">Factura interna {{ $documento['numero'] }}</h1>
    <p>Hola {{ $documento['clienteNombre'] }},</p>
    <p>Adjuntamos la factura interna de servicio por <strong>$ {{ number_format((float) $documento['total'], 2, ',', '.') }} {{ $documento['moneda'] }}</strong>.</p>
    <p>Este es un documento de control interno del taller y no sustituye una factura electrónica DIAN.</p>
    <p>AUTOFIX</p>
</body></html>
