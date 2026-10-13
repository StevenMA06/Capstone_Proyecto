<?php
namespace App\Http\Controllers;
use App\Http\Requests\StorePretestObservationRequest;
use App\Services\PretestMeasurementService;

class PretestObservationController extends Controller
{
    public function store(StorePretestObservationRequest $request,PretestMeasurementService $service)
    {
        $data=$request->validated();
        $service->record($data,(int)$request->user()->getKey());
        return redirect()->route('indicators.index',['desde'=>substr($data['fin'],0,7).'-01','hasta'=>substr($data['fin'],0,10)])
            ->with('status','Observación del pretest registrada. Los casos excepcionales se conservan separados del promedio ordinario.');
    }
}
