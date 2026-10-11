<?php
namespace App\Http\Controllers;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics)
    {
        $values=$request->validate([
            'desde'=>['nullable','date_format:Y-m-d'],
            'hasta'=>['nullable','date_format:Y-m-d','after_or_equal:desde'],
            'proceso'=>['nullable',Rule::in(['all',...array_keys(AnalyticsService::PROCESSES)])],
        ]);
        $from=$values['desde'] ?? now()->startOfMonth()->toDateString();
        $to=$values['hasta'] ?? now()->toDateString();
        if ($from > $to) throw \Illuminate\Validation\ValidationException::withMessages(['hasta'=>'La fecha final debe ser igual o posterior a la inicial.']);
        if (\Carbon\Carbon::parse($from)->diffInMonths(\Carbon\Carbon::parse($to)) > 60) throw \Illuminate\Validation\ValidationException::withMessages(['hasta'=>'Selecciona un período de hasta cinco años.']);
        $process=$values['proceso'] ?? 'all';
        return view('analytics.screen', [
            'screen'=>$request->routeIs('bi.index') ? 'bi' : 'indicators',
            'from'=>$from,'to'=>$to,'process'=>$process,'processes'=>AnalyticsService::PROCESSES,
            ...$analytics->dashboard($from,$to,$process),
        ]);
    }
}
