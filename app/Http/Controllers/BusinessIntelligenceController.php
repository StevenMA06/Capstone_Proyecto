<?php
namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsQueryRequest;
use App\Services\BusinessIntelligenceService;
use App\Services\AnalyticsService;
use App\Services\IndicatorQueryService;
use Illuminate\Validation\ValidationException;

class BusinessIntelligenceController extends Controller
{
    public function filterByProcess(AnalyticsQueryRequest $request,AnalyticsService $service)
    {
        [$from,$to]=$request->period(); $process=$request->validated('proceso','all');
        $data=$service->dashboard($from,$to,$process);
        return response()->json(['success'=>true,'proceso_filtrado'=>$process,'periodo'=>['inicio'=>$from,'fin'=>$to],
            'indicadores'=>$data['items'],'situacion_actual'=>['alertas'=>$data['alerts'],'materiales'=>$data['materials']],
            'nota'=>'La situación actual y los estados vigentes se distinguen de la actividad del período.']);
    }
    public function staffParticipation(AnalyticsQueryRequest $request,BusinessIntelligenceService $service)
    {
        [$from,$to]=$request->period();
        return response()->json(['success'=>true,'periodo'=>['inicio'=>$from,'fin'=>$to],...$service->staff($from,$to,(int)$request->validated('top',10),$request->filled('empleado_id')?(int)$request->validated('empleado_id'):null)]);
    }
    public function generateChart(AnalyticsQueryRequest $request,BusinessIntelligenceService $service)
    {
        [$from,$to]=$request->period(); $indicator=$request->validated('indicador','clientes');
        $available=$service->available($request->validated('proceso','all'));
        if(!isset($available[$indicator])) throw ValidationException::withMessages(['indicador'=>'El indicador no pertenece al proceso seleccionado.']);
        $chart=$service->chart($indicator,$request->validated('agrupacion','mes'),$request->validated('tipo_grafico','bar'),$request->validated('medida','cantidad'),$from,$to);
        return response()->json(['success'=>true,'chart'=>$chart]);
    }
    public function registrationEvolution(AnalyticsQueryRequest $request,BusinessIntelligenceService $service)
    {
        if(!in_array($request->validated('agrupacion','mes'),['dia','semana','mes'],true)) throw ValidationException::withMessages(['agrupacion'=>'La evolución requiere día, semana o mes.']);
        if(in_array($request->validated('tipo_grafico','bar'),['pie','doughnut'],true)) throw ValidationException::withMessages(['tipo_grafico'=>'Para evolución utiliza líneas, barras o área.']);
        return $this->generateChart($request,$service);
    }
    public function avgInventoryTime(AnalyticsQueryRequest $request,IndicatorQueryService $service)
    {
        [$from,$to]=$request->period();
        return response()->json($service->measurement('inventario',$from,$to));
    }
    public function timeByModule(AnalyticsQueryRequest $request,IndicatorQueryService $service)
    {
        [$from,$to]=$request->period();
        return response()->json($service->inventoryByType($from,$to));
    }
}
