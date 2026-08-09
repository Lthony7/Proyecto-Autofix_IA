<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $documento['numero'] }}</title>
    <style>
        @page { margin: 34px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.4; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 2px solid #243b64; margin-bottom: 20px; padding-bottom: 16px; }
        .brand { color: #243b64; font-size: 22px; font-weight: bold; }
        .right { float: right; text-align: right; }
        .muted { color: #657087; }
        .grid { display: table; margin-bottom: 18px; width: 100%; }
        .cell { display: table-cell; width: 50%; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #edf1f7; color: #243b64; text-align: left; }
        th, td { border-bottom: 1px solid #d8dee9; padding: 7px 6px; }
        .number { text-align: right; white-space: nowrap; }
        .totals { margin-left: 55%; margin-top: 18px; width: 45%; }
        .totals td { border: 0; padding: 4px 0; }
        .total td { border-top: 2px solid #243b64; font-size: 14px; font-weight: bold; padding-top: 8px; }
        .notice { border-top: 1px solid #d8dee9; color: #657087; font-size: 9px; margin-top: 28px; padding-top: 10px; text-align: center; }
        .state { font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    <header class="header">
        <div class="right">
            <strong>Factura {{ $documento['numero'] }}</strong><br>
            <span>Versión {{ $documento['version'] }}</span><br>
            <span class="state">{{ $documento['estado'] }}</span>
        </div>
        <div class="brand">AUTOFIX</div>
        <div>Taller automotriz</div>
        <div class="muted">Factura interna de servicio</div>
    </header>

    <section class="grid">
        <div class="cell">
            <strong>Cliente</strong><br>
            {{ $documento['clienteNombre'] }}<br>
            {{ $documento['clienteTipoDocumento'] }} {{ $documento['clienteDocumento'] }}<br>
            {{ $documento['clienteDireccion'] }}<br>
            {{ $documento['clienteEmail'] }}
        </div>
        <div class="cell right">
            <strong>Emisión:</strong> {{ $documento['emitidaEn'] }}<br>
            <strong>Vencimiento:</strong> {{ $documento['venceEn'] ?: 'Sin vencimiento' }}<br>
            <strong>Vehículo:</strong> {{ $documento['vehiculoPlaca'] }}
        </div>
    </section>

    <table>
        <thead><tr><th>Concepto</th><th class="number">Cantidad</th><th class="number">Precio</th><th class="number">Subtotal</th></tr></thead>
        <tbody>
        @foreach ($documento['lineas'] as $linea)
            <tr>
                <td><span class="muted">{{ $linea['tipo'] }}{{ $linea['codigo'] ? ' · '.$linea['codigo'] : '' }}</span><br>{{ $linea['descripcion'] }}</td>
                <td class="number">{{ $linea['cantidad'] }}</td>
                <td class="number">$ {{ number_format((float) $linea['precioUnitario'], 2, ',', '.') }}</td>
                <td class="number">$ {{ number_format((float) $linea['subtotal'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="number">$ {{ number_format((float) $documento['subtotal'], 2, ',', '.') }}</td></tr>
        <tr><td>Descuento</td><td class="number">-$ {{ number_format((float) $documento['descuento'], 2, ',', '.') }}</td></tr>
        <tr><td>Base gravable</td><td class="number">$ {{ number_format((float) $documento['baseImpuesto'], 2, ',', '.') }}</td></tr>
        <tr><td>Impuesto ({{ $documento['tasaImpuesto'] }}%)</td><td class="number">$ {{ number_format((float) $documento['impuesto'], 2, ',', '.') }}</td></tr>
        <tr class="total"><td>Total</td><td class="number">$ {{ number_format((float) $documento['total'], 2, ',', '.') }} {{ $documento['moneda'] }}</td></tr>
    </table>

    @if ($documento['observaciones'])<p><strong>Observaciones:</strong> {{ $documento['observaciones'] }}</p>@endif
    @if ($documento['motivoAnulacion'])<p><strong>Anulación:</strong> {{ $documento['motivoAnulacion'] }}</p>@endif
    <footer class="notice">Documento de control interno del taller. No sustituye una factura electrónica DIAN.</footer>
</body>
</html>
