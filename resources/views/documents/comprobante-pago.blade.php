<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante {{ $documento['comprobanteNumero'] }}</title>
    <style>
        @page { margin: 34px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.4; }
        h1, p { margin: 0; }
        .header { border-bottom: 2px solid #176b5b; margin-bottom: 20px; padding-bottom: 16px; }
        .brand { color: #176b5b; font-size: 22px; font-weight: bold; }
        .right { float: right; text-align: right; }
        .muted { color: #657087; }
        .grid { display: table; margin-bottom: 18px; width: 100%; }
        .cell { display: table-cell; width: 50%; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #eaf5f2; color: #176b5b; text-align: left; }
        th, td { border-bottom: 1px solid #d8dee9; padding: 7px 6px; }
        .number { text-align: right; white-space: nowrap; }
        .totals { margin-left: 50%; margin-top: 18px; width: 50%; }
        .totals td { border: 0; padding: 3px 0; }
        .payment td { border-top: 2px solid #176b5b; color: #176b5b; font-size: 14px; font-weight: bold; padding-top: 8px; }
        .notice { border-top: 1px solid #d8dee9; color: #657087; font-size: 9px; margin-top: 28px; padding-top: 10px; text-align: center; }
        .state { font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    <header class="header">
        <div class="right">
            <strong>Comprobante {{ $documento['comprobanteNumero'] }}</strong><br>
            {{ $documento['pagadoEn'] }}<br>
            <span class="state">{{ $documento['estado'] }}</span>
        </div>
        <div class="brand">AUTOFIX</div>
        <div>Taller automotriz</div>
        <div class="muted">Comprobante de pago</div>
    </header>

    <section class="grid">
        <div class="cell">
            <strong>Cliente</strong><br>
            {{ $documento['clienteNombre'] }}<br>
            {{ $documento['clienteTipoDocumento'] }} {{ $documento['clienteDocumento'] }}
        </div>
        <div class="cell right">
            <strong>Orden:</strong> {{ $documento['ordenNumero'] }}<br>
            <strong>Factura:</strong> {{ $documento['facturaNumero'] }}<br>
            <strong>Vehículo:</strong> {{ $documento['vehiculoPlaca'] }} · {{ $documento['vehiculoDescripcion'] }}
        </div>
    </section>

    <table>
        <thead><tr><th>Concepto</th><th class="number">Cantidad</th><th class="number">Precio</th><th class="number">Subtotal</th></tr></thead>
        <tbody>
        @foreach ($documento['detalle'] as $linea)
            <tr>
                <td><span class="muted">{{ $linea['tipo'] }}{{ empty($linea['codigo']) ? '' : ' · '.$linea['codigo'] }}</span><br>{{ $linea['descripcion'] }}</td>
                <td class="number">{{ $linea['cantidad'] }}</td>
                <td class="number">$ {{ number_format((float) $linea['precioUnitario'], 2, ',', '.') }}</td>
                <td class="number">$ {{ number_format((float) $linea['subtotal'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Servicios</td><td class="number">$ {{ number_format((float) $documento['servicios'], 2, ',', '.') }}</td></tr>
        <tr><td>Repuestos</td><td class="number">$ {{ number_format((float) $documento['repuestos'], 2, ',', '.') }}</td></tr>
        <tr><td>Descuento</td><td class="number">-$ {{ number_format((float) $documento['descuento'], 2, ',', '.') }}</td></tr>
        <tr><td>Impuestos</td><td class="number">$ {{ number_format((float) $documento['impuesto'], 2, ',', '.') }}</td></tr>
        <tr><td>Total orden</td><td class="number">$ {{ number_format((float) $documento['totalOrden'], 2, ',', '.') }}</td></tr>
        <tr class="payment"><td>Este pago</td><td class="number">$ {{ number_format((float) $documento['monto'], 2, ',', '.') }} {{ $documento['moneda'] }}</td></tr>
        <tr><td>Pagado acumulado</td><td class="number">$ {{ number_format((float) $documento['pagadoAcumulado'], 2, ',', '.') }}</td></tr>
        <tr><td>Saldo pendiente</td><td class="number">$ {{ number_format((float) $documento['saldoResultante'], 2, ',', '.') }}</td></tr>
    </table>

    <p><strong>Método:</strong> {{ $documento['metodo'] }} · <strong>Referencia:</strong> {{ $documento['referencia'] ?: 'No aplica' }}</p>
    @if ($documento['observaciones'])<p><strong>Observaciones:</strong> {{ $documento['observaciones'] }}</p>@endif
    @if ($documento['motivoAnulacion'])<p><strong>Anulación:</strong> {{ $documento['motivoAnulacion'] }}</p>@endif
    @if ($documento['motivoReembolso'])<p><strong>Reembolso:</strong> {{ $documento['motivoReembolso'] }}</p>@endif
    <footer class="notice">Documento de control interno del taller. No sustituye una factura electrónica DIAN.</footer>
</body>
</html>
