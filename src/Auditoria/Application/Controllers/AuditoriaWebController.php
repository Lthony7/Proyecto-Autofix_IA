<?php
namespace Src\Auditoria\Application\Controllers;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Inertia\Inertia;use Inertia\Response;use Src\Auditoria\Infrastructure\Models\AuditoriaEloquentModel;use Src\Auth\Infrastructure\Models\UserEloquentModel;
class AuditoriaWebController extends Controller
{
    public function index(Request$r):Response{$f=$r->validate(['buscar'=>'nullable|string|max:120','usuario'=>'nullable|uuid|exists:users,id','desde'=>'nullable|date','hasta'=>'nullable|date|after_or_equal:desde']);$buscar=trim((string)($f['buscar']??''));$registros=AuditoriaEloquentModel::with('usuario:id,name,email')->when($buscar,fn($q)=>$q->where(fn($s)=>$s->where('accion','ilike',"%{$buscar}%")->orWhere('recurso_tipo','ilike',"%{$buscar}%")))->when($f['usuario']??null,fn($q,$id)=>$q->where('usuario_id',$id))->when($f['desde']??null,fn($q,$fecha)=>$q->whereDate('created_at','>=',$fecha))->when($f['hasta']??null,fn($q,$fecha)=>$q->whereDate('created_at','<=',$fecha))->latest('created_at')->paginate(10)->withQueryString();return Inertia::render('Auditoria/index',['registros'=>$registros,'usuarios'=>UserEloquentModel::orderBy('name')->get(['id','name','email']),'filtros'=>['buscar'=>$buscar,'usuario'=>$f['usuario']??'','desde'=>$f['desde']??'','hasta'=>$f['hasta']??'']]);}
}
