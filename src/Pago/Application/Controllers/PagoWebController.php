<?php
namespace Src\Pago\Application\Controllers;
use App\Http\Controllers\Controller;use Illuminate\Http\RedirectResponse;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Inertia\Inertia;use Inertia\Response;use Src\Auditoria\Application\Services\RegistrarAuditoria;use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;use Src\Pago\Application\Services\CalculadorTotalOrden;use Src\Pago\Application\Services\GestionarPago;use Src\Pago\Infrastructure\Models\PagoEloquentModel;use Src\Pago\Infrastructure\Requests\AnularPagoRequest;use Src\Pago\Infrastructure\Requests\ReembolsarPagoRequest;use Src\Pago\Infrastructure\Requests\RegistrarPagoRequest;
class PagoWebController extends Controller
{
    public function index(Request $r, CalculadorTotalOrden $calculador): Response
    {
        $buscar = trim((string) $r->input('buscar'));
        $pagos = PagoEloquentModel::with(['orden.cliente:id,razon_social', 'orden.vehiculo:id,placa'])
            ->whereHas('orden', fn ($q) => $q->visiblePara($r->user()))
            ->when($buscar, fn ($q) => $q->where(function ($s) use ($buscar) {
                $s->where('numero', 'ilike', "%{$buscar}%")
                    ->orWhere('comprobante_numero', 'ilike', "%{$buscar}%")
                    ->orWhere('referencia', 'ilike', "%{$buscar}%")
                    ->orWhereHas('orden', fn ($o) => $o->where('numero', 'ilike', "%{$buscar}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('razon_social', 'ilike', "%{$buscar}%")));
            }))
            ->latest('pagado_en')
            ->paginate(20)
            ->withQueryString();

        $ordenesPendientes = collect();
        if ($r->user()->can('pagos.registrar')) {
            $ordenesPendientes = OrdenTrabajoEloquentModel::query()
                ->visiblePara($r->user())
                ->with(['cliente:id,razon_social', 'vehiculo:id,placa,marca,modelo'])
                ->whereNot('estado', 'cancelada')
                ->latest('recibida_en')
                ->limit(100)
                ->get()
                ->map(function (OrdenTrabajoEloquentModel $orden) use ($calculador) {
                    $finanzas = $calculador->calcular($orden->id);

                    return [
                        'id' => $orden->id,
                        'numero' => $orden->numero,
                        'cliente' => $orden->cliente?->razon_social,
                        'vehiculo' => trim("{$orden->vehiculo?->placa} · {$orden->vehiculo?->marca} {$orden->vehiculo?->modelo}"),
                        'total' => $finanzas['total'],
                        'pagado' => $finanzas['pagado'],
                        'saldo' => $finanzas['saldo'],
                    ];
                })
                ->filter(fn (array $orden) => (float) $orden['saldo'] > 0)
                ->values();
        }

        return Inertia::render('Pago/index', [
            'pagos' => $pagos,
            'buscar' => $buscar,
            'ordenesPendientes' => $ordenesPendientes,
        ]);
    }
    public function comprobante(Request $r, PagoEloquentModel $pago, CalculadorTotalOrden $calculador, RegistrarAuditoria $auditoria): Response
    {
        $pago->load(['orden.cliente', 'orden.vehiculo']);
        $this->autorizarOrden($r, $pago->orden);
        $factura = FacturaOrdenEloquentModel::with('lineas')->where('orden_id', $pago->orden_id)->where('estado', 'emitida')->first();
        $conceptos = $pago->detalle_snapshot !== null ? collect($pago->detalle_snapshot) : ($factura?->lineas?->map(fn ($linea) => [
            'tipo' => $linea->tipo,
            'codigo' => $linea->codigo,
            'descripcion' => $linea->descripcion,
            'cantidad' => $linea->cantidad,
            'precioUnitario' => $linea->precio_unitario,
            'subtotal' => $linea->subtotal,
        ]) ?? collect());
        if ($conceptos->isEmpty()) {
            $conceptos = DB::table('orden_servicios')->where('orden_id', $pago->orden_id)->where('estado', '<>', 'cancelado')->get()->map(fn ($linea) => [
                'tipo' => 'servicio', 'codigo' => null, 'descripcion' => $linea->nombre_servicio, 'cantidad' => '1.000',
                'precioUnitario' => $linea->precio_acordado, 'subtotal' => $linea->precio_acordado,
            ]);
            $repuestos = DB::table('orden_repuestos as uso')->join('repuestos as repuesto', 'repuesto.id', '=', 'uso.repuesto_id')->where('uso.orden_id', $pago->orden_id)->whereNull('uso.revertido_en')->get();
            $conceptos = $conceptos->concat($repuestos->map(fn ($linea) => [
                'tipo' => 'repuesto', 'codigo' => $linea->codigo, 'descripcion' => $linea->nombre, 'cantidad' => $linea->cantidad,
                'precioUnitario' => $linea->precio_unitario, 'subtotal' => (string) \Brick\Math\BigDecimal::of((string) $linea->cantidad)->multipliedBy(\Brick\Math\BigDecimal::of((string) $linea->precio_unitario))->toScale(2, \Brick\Math\RoundingMode::HALF_UP),
            ]));
        }
        $finanzas = $calculador->calcular($pago->orden_id);
        if ($pago->total_orden_snapshot !== null) {
            $finanzas['servicios'] = $pago->servicios_snapshot ?? $finanzas['servicios'];
            $finanzas['repuestos'] = $pago->repuestos_snapshot ?? $finanzas['repuestos'];
            $finanzas['descuento'] = $pago->descuento_snapshot ?? $finanzas['descuento'];
            $finanzas['impuesto'] = $pago->impuesto_snapshot ?? $finanzas['impuesto'];
            $finanzas['total'] = $pago->total_orden_snapshot;
            $finanzas['pagado'] = $pago->pagado_acumulado_snapshot;
            $finanzas['saldo'] = $pago->saldo_resultante_snapshot;
            $finanzas['estado'] = (float) $pago->saldo_resultante_snapshot <= 0 ? 'pagado' : 'parcial';
        }
        $auditoria->registrar('pago.comprobante_consultado', 'pago', $pago->id, [], $r);
        return Inertia::render('Pago/comprobante', [
            'pago' => $pago,
            'conceptos' => $conceptos->values(),
            'finanzas' => $finanzas,
            'factura' => $factura ? ['numero' => $factura->numero, 'descuento' => $factura->descuento, 'impuesto' => $factura->impuesto, 'total' => $factura->total] : null,
            'reconstruido' => $pago->detalle_snapshot === null,
        ]);
    }
    public function store(RegistrarPagoRequest$r,OrdenTrabajoEloquentModel$orden,GestionarPago$servicio,RegistrarAuditoria$a):RedirectResponse{$this->autorizarOrden($r,$orden);$pago=$servicio->registrar($orden,$r->validated(),$r->user()->id);$a->registrar('pago.registrado','pago',$pago->id,['orden_id'=>$orden->id,'monto'=>$pago->monto],$r);if($r->boolean('ver_comprobante'))return redirect()->route('pagos.comprobante',$pago)->with('success',"Pago {$pago->numero} registrado y comprobante generado.");return back()->with('success',"Pago {$pago->numero} registrado.");}
    public function anular(AnularPagoRequest$r,PagoEloquentModel$pago,GestionarPago$servicio,RegistrarAuditoria$a):RedirectResponse{$orden=OrdenTrabajoEloquentModel::findOrFail($pago->orden_id);$this->autorizarOrden($r,$orden);$servicio->anular($pago,$r->validated('motivo'),$r->user()->id);$a->registrar('pago.anulado','pago',$pago->id,['motivo'=>$r->validated('motivo')],$r);return back()->with('success','Pago anulado; el saldo de la orden fue restablecido.');}
    public function reembolsar(ReembolsarPagoRequest$r,PagoEloquentModel$pago,GestionarPago$servicio,RegistrarAuditoria$a):RedirectResponse{$orden=OrdenTrabajoEloquentModel::findOrFail($pago->orden_id);$this->autorizarOrden($r,$orden);$servicio->reembolsar($pago,$r->validated('motivo'),$r->user()->id);$a->registrar('pago.reembolsado','pago',$pago->id,['motivo'=>$r->validated('motivo'),'monto'=>$pago->monto],$r);return back()->with('success','Pago reembolsado; el saldo de la orden fue restablecido.');}
    private function autorizarOrden(Request$r,OrdenTrabajoEloquentModel$o):void{abort_unless(OrdenTrabajoEloquentModel::whereKey($o->id)->visiblePara($r->user())->exists(),403);}
}
