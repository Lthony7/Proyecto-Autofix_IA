<?php
namespace Src\OrdenTrabajo\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;
class GuardarOrdenRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('ordenes.crear') ?? false; }
    protected function prepareForValidation(): void { $this->merge(['cliente_id'=>$this->input('clienteId'),'vehiculo_id'=>$this->input('vehiculoId'),'servicio_ids'=>$this->input('servicioIds',[]),'mecanico_ids'=>$this->input('mecanicoIds',[]),'falla_reportada'=>trim((string)$this->input('fallaReportada'))]); }
    public function rules(): array { return ['cliente_id'=>['required','uuid',Rule::exists('clientes','id')->where('estado','activo')],'vehiculo_id'=>['required','uuid',Rule::exists('vehiculos','id')->where('estado','activo')],'falla_reportada'=>['required','string','min:10','max:5000'],'kilometraje'=>['nullable','integer','min:0','max:9999999'],'servicio_ids'=>['required','array','min:1'],'servicio_ids.*'=>['uuid','distinct',Rule::exists('servicios_taller','id')->where('estado','activo')],'mecanico_ids'=>['array'],'mecanico_ids.*'=>['uuid','distinct',Rule::exists('mecanicos','id')->where('estado','activo')]]; }
    public function after(): array { return [function($v){ if($this->input('cliente_id')&&$this->input('vehiculo_id')&&!VehiculoEloquentModel::whereKey($this->input('vehiculo_id'))->where('cliente_id',$this->input('cliente_id'))->exists())$v->errors()->add('vehiculoId','El vehículo no pertenece al cliente.'); }]; }
}
