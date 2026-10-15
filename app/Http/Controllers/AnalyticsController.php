<?php
namespace App\Http\Controllers;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function index(\App\Http\Requests\AnalyticsQueryRequest $request, AnalyticsService $analytics)
    {
        $values=$request->validated();
        $from=$values['desde'] ?? now()->startOfMonth()->toDateString();
        $to=$values['hasta'] ?? now()->toDateString();
        $process=$values['proceso'] ?? 'all';
        $bi=app(\App\Services\BusinessIntelligenceService::class);
        $available=$bi->available($process);
        $indicator=$values['indicador'] ?? array_key_first($available);
        if(!isset($available[$indicator])) $indicator=array_key_first($available);
        $isBi=$request->routeIs('bi.index');
        $explorer=$isBi ? $bi->chart($indicator,$values['agrupacion'] ?? 'mes',$values['tipo_grafico'] ?? 'bar',$values['medida'] ?? 'cantidad',$from,$to) : null;
        return view('analytics.screen', [
            'explorer'=>$explorer,'chartIndicators'=>$available,
            'sales'=>($isBi && in_array($process,['all','contratos'],true)) ? $bi->sales($from,$to) : null,
            'staff'=>($isBi && in_array($process,['all','contratos'],true)) ? $bi->staff($from,$to,(int)($values['top'] ?? 10),isset($values['empleado_id'])?(int)$values['empleado_id']:null) : null,
            'screen'=>$request->routeIs('bi.index') ? 'bi' : 'indicators',
            'pretest'=>app(\App\Services\PretestMeasurementService::class)->summary($from,$to,$process),
            'from'=>$from,'to'=>$to,'process'=>$process,'processes'=>AnalyticsService::PROCESSES,
            ...$analytics->dashboard($from,$to,$process),
        ]);
    }
    public function avgClientRegistrationTime(\App\Http\Requests\AnalyticsQueryRequest $request, \App\Services\IndicatorQueryService $service)
    {
        [$from,$to]=$request->period();
        return response()->json($service->measurement('clientes',$from,$to));
    }
    public function avgContractRegistrationTime(\App\Http\Requests\AnalyticsQueryRequest $request, \App\Services\IndicatorQueryService $service)
    {
        [$from,$to]=$request->period();
        if($request->filled('empleado_id')) throw \Illuminate\Validation\ValidationException::withMessages(['empleado_id'=>'Las observaciones no identifican al empleado ejecutor. El observador no se usa como sustituto.']);
        return response()->json($service->measurement('contratos',$from,$to));
    }
    public function avgReactivationTime(\App\Http\Requests\AnalyticsQueryRequest $request, \App\Services\IndicatorQueryService $service)
    {
        [$from,$to]=$request->period();
        return response()->json($service->measurement('reactivacion',$from,$to));
    }
    public function avgOrderProcessingTime(\App\Http\Requests\AnalyticsQueryRequest $request, \App\Services\IndicatorQueryService $service)
    {
        [$from,$to]=$request->period();
        return response()->json($service->measurement('instalaciones',$from,$to));
    }
    public function avgInformationAccessTime(\App\Http\Requests\AnalyticsQueryRequest $request, \App\Services\IndicatorQueryService $service)
    {
        [$from,$to]=$request->period();
        return response()->json($service->measurement('acceso_informacion',$from,$to));
    }
    public function avgInventoryRegistrationTime(\App\Http\Requests\AnalyticsQueryRequest $request, \App\Services\IndicatorQueryService $service)
    {
        [$from,$to]=$request->period();
        return response()->json($service->measurement('inventario',$from,$to));
    }
    public function inventoryTimeByType(\App\Http\Requests\AnalyticsQueryRequest $request, \App\Services\IndicatorQueryService $service)
    {
        [$from,$to]=$request->period();
        return response()->json($service->inventoryByType($from,$to));
    }
}
