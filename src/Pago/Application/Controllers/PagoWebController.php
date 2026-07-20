<?php
namespace Src\Pago\Application\Controllers;
use App\Http\Controllers\Controller;use Illuminate\Http\RedirectResponse;use Illuminate\Http\Request;use Inertia\Inertia;use Inertia\Response;use Src\Auditoria\Application\Services\RegistrarAuditoria;use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;use Src\Pago\Application\Services\GestionarPago;use Src\Pago\Infrastructure\Models\PagoEloquentModel;use Src\Pago\Infrastructure\Requests\AnularPagoRequest;use Src\Pago\Infrastructure\Requests\RegistrarPagoRequest;
class PagoWebController extends Controller
{
    public function index(Request $r): Response
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

        return Inertia::render('Pago/index', ['pagos' => $pagos, 'buscar' => $buscar]);
    }
    public function store(RegistrarPagoRequest$r,OrdenTrabajoEloquentModel$orden,GestionarPago$servicio,RegistrarAuditoria$a):RedirectResponse{$this->autorizarOrden($r,$orden);$pago=$servicio->registrar($orden,$r->validated(),$r->user()->id);$a->registrar('pago.registrado','pago',$pago->id,['orden_id'=>$orden->id,'monto'=>$pago->monto],$r);return back()->with('success',"Pago {$pago->numero} registrado.");}
    public function anular(AnularPagoRequest$r,PagoEloquentModel$pago,GestionarPago$servicio,RegistrarAuditoria$a):RedirectResponse{$orden=OrdenTrabajoEloquentModel::findOrFail($pago->orden_id);$this->autorizarOrden($r,$orden);$servicio->anular($pago,$r->validated('motivo'),$r->user()->id);$a->registrar('pago.anulado','pago',$pago->id,['motivo'=>$r->validated('motivo')],$r);return back()->with('success','Pago anulado; el saldo de la orden fue restablecido.');}
    private function autorizarOrden(Request$r,OrdenTrabajoEloquentModel$o):void{abort_unless(OrdenTrabajoEloquentModel::whereKey($o->id)->visiblePara($r->user())->exists(),403);}
}
