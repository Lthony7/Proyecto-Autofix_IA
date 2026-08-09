<?php

namespace Src\Facturacion\Application\Controllers;

use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Facturacion\Application\Services\GestionarFacturaOrden;
use Src\Facturacion\Application\Services\GenerarFacturaPdf;
use Src\Facturacion\Application\Jobs\EnviarFacturaPorCorreo;
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
        return Inertia::render('Facturacion/show', ['factura' => $factura->load(['orden:id,numero', 'reemplaza:id,numero', 'lineas', 'historial' => fn ($q) => $q->latest('created_at')])]);
    }

    public function pdf(Request $request, FacturaOrdenEloquentModel $factura, GenerarFacturaPdf $generador): HttpResponse
    {
        $this->autorizar($request, $factura->orden_id);
        $pdf = $generador->generar($factura);
        $nombre = 'factura-'.preg_replace('/[^A-Za-z0-9._-]/', '-', $factura->numero).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$nombre.'"',
            'Content-Length' => (string) strlen($pdf),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function enviar(Request $request, FacturaOrdenEloquentModel $factura, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $this->autorizar($request, $factura->orden_id);
        if (filter_var($factura->cliente_email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['email' => 'La factura no tiene un correo de cliente válido en su instantánea.']);
        }

        EnviarFacturaPorCorreo::dispatch($factura->id, $request->user()->id)->afterCommit();
        $auditoria->registrar('factura_orden.correo_encolado', 'factura_orden', $factura->id, ['destinatario' => $factura->cliente_email], $request);

        return back()->with('success', "La factura se enviará a {$factura->cliente_email}.");
    }

    public function store(EmitirFacturaOrdenRequest $request, OrdenTrabajoEloquentModel $orden, GestionarFacturaOrden $servicio, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $this->autorizar($request, $orden->id);
        $datos = $request->validated();
        if ((float) $datos['descuento'] > 0) $datos = [...$datos, 'descuento_autorizado_por' => $request->user()->id, 'descuento_autorizado_en' => now()];
        $factura = DB::transaction(function () use ($servicio, $orden, $datos, $request, $auditoria) {
            $factura = $servicio->emitir($orden, $datos, $request->user()->id);
            $pagado = BigDecimal::of((string) DB::table('pagos')->where('orden_id', $orden->id)->where('estado', 'registrado')->sum('monto'));
            if (BigDecimal::of((string) $factura->total)->isLessThan($pagado)) throw \Illuminate\Validation\ValidationException::withMessages(['descuento' => 'El total facturado no puede ser menor que los pagos ya registrados.']);
            $auditoria->registrar('factura_orden.emitida', 'factura_orden', $factura->id, ['orden_id' => $orden->id, 'total' => $factura->total, 'descuento' => $factura->descuento], $request);
            return $factura;
        });
        return redirect($request->user()->can('facturas.ver') ? route('facturacion.show', $factura) : route('ordenes.show', $orden))->with('success', "Factura {$factura->numero} emitida.");
    }

    public function anular(AnularFacturaOrdenRequest $request, FacturaOrdenEloquentModel $factura, GestionarFacturaOrden $servicio, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $this->autorizar($request, $factura->orden_id);
        DB::transaction(function () use ($servicio, $factura, $request, $auditoria) {
            $servicio->anular($factura, $request->validated('motivo'), $request->user()->id);
            $auditoria->registrar('factura_orden.anulada', 'factura_orden', $factura->id, ['motivo' => $request->validated('motivo')], $request);
        });
        return back()->with('success', 'Factura anulada sin eliminar su historial.');
    }

    private function autorizar(Request $request, string $ordenId): void
    {
        abort_unless(OrdenTrabajoEloquentModel::whereKey($ordenId)->visiblePara($request->user())->exists(), 403);
    }
}
