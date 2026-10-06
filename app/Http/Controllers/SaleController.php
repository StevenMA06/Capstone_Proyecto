<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleFilterRequest;
use App\Services\SaleService;

class SaleController extends Controller
{
    public function __construct(private SaleService $sales) {}

    public function confirm(\Illuminate\Http\Request $request, int $contract, \App\Services\ContractService $contracts)
    {
        $data = $request->validate(['documento_pdf'=>['required','file','mimes:pdf','max:10240']]);
        $feedback = \Illuminate\Support\Facades\DB::transaction(function () use ($contract,$contracts,$data,$request) {
            $record = \App\Models\Contract::lockForUpdate()->findOrFail($contract);
            if ($record->estado !== 'PENDIENTE') throw \Illuminate\Validation\ValidationException::withMessages(['documento_pdf'=>'Solo puedes confirmar la firma de un contrato pendiente.']);
            // Take all terms from the stored contract. Posted fields cannot alter conditions or cancel it.
            $terms = $record->only(['cliente_id','plan_id','zona_id','responsable_id','duracion_meses','observaciones']);
            $terms['fecha_inicio'] = \Carbon\Carbon::parse($record->fecha_inicio)->format('Y-m-d');
            return $contracts->update($contract, $terms + $data, $request->user()->getKey());
        }, 3);
        return redirect()->route('contracts.show',$contract)->with('status',$feedback['message'])->with('status_kind',$feedback['kind']);
    }

    public function index(SaleFilterRequest $request)
    {
        return view('sales.index', $this->sales->dashboard($request->validated()));
    }
}
