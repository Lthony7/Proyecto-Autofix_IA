<?php

namespace Src\Facturacion\Application\Controllers;

use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Facturacion\Application\Services\GestionarFacturaOrden;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;
use Src\Facturacion\Infrastructure\Requests\AnularFacturaOrdenRequest;
use Src\Facturacion\Infrastructure\Requests\EmitirFacturaOrdenRequest;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;

class FacturacionWebController extends Controller
{
    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->input('buscar'));
        $facturas = FacturaOrdenEloquentModel::with('orden:id,numero')->whereHas('orden', fn ($q) => $q->visiblePara($request->user()))
            ->when($buscar, fn ($q) => $q->where(fn ($s) => $s->where('numero', 'ilike', "%{$buscar}%")->orWhere('cliente_nombre', 'ilike', "%{$buscar}%")->orWhere('cliente_documento', 'ilike', "%{$buscar}%")->orWhereHas('orden', fn ($o) => $o->where('numero', 'ilike', "%{$buscar}%"))))
            ->latest('emitida_en')->paginate(20)->withQueryString();
        return Inertia::render('Facturacion/index', ['facturas' => $facturas, 'buscar' => $buscar]);
    }

    public function show(Request $request, FacturaOrdenEloquentModel $factura): Response
    {
        $this->autorizar($request, $factura->orden_id);
        return Inertia::render('Facturacion/show', ['factura' => $factura->load(['orden:id,numero', 'lineas', 'historial' => fn ($q) => $q->latest('created_at')])]);
    }

    public function store(EmitirFacturaOrdenRequest $request, OrdenTrabajoEloquentModel $orden, GestionarFacturaOrden $servicio, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $this->autorizar($request, $orden->id);
        $datos = $request->validated();
        $factura = DB::transaction(function () use ($servicio, $orden, $datos, $request) {
            $factura = $servicio->emitir($orden, $datos, $request->user()->id);
            $pagado = BigDecimal::of((string) DB::table('pagos')->where('orden_id', $orden->id)->where('estado', 'registrado')->sum('monto'));
            if (BigDecimal::of((string) $factura->total)->isLessThan($pagado)) throw \Illuminate\Validation\ValidationException::withMessages(['descuento' => 'El total facturado no puede ser menor que los pagos ya registrados.']);
            if ((float) $datos['descuento'] > 0) $factura->update(['motivo_descuento' => $datos['motivo_descuento'], 'descuento_autorizado_por' => $request->user()->id, 'descuento_autorizado_en' => now()]);
            return $factura;
        });
        $auditoria->registrar('factura_orden.emitida', 'factura_orden', $factura->id, ['orden_id' => $orden->id, 'total' => $factura->total, 'descuento' => $factura->descuento], $request);
        return redirect($request->user()->can('facturas.ver') ? route('facturacion.show', $factura) : route('ordenes.show', $orden))->with('success', "Factura {$factura->numero} emitida.");
    }

    public function anular(AnularFacturaOrdenRequest $request, FacturaOrdenEloquentModel $factura, GestionarFacturaOrden $servicio, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $this->autorizar($request, $factura->orden_id);
        $servicio->anular($factura, $request->validated('motivo'), $request->user()->id);
        $auditoria->registrar('factura_orden.anulada', 'factura_orden', $factura->id, ['motivo' => $request->validated('motivo')], $request);
        return back()->with('success', 'Factura anulada sin eliminar su historial.');
    }

    private function autorizar(Request $request, string $ordenId): void
    {
        abort_unless(OrdenTrabajoEloquentModel::whereKey($ordenId)->visiblePara($request->user())->exists(), 403);
    }
}
