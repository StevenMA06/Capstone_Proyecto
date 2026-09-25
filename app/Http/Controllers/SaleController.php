<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleFilterRequest;
use App\Services\SaleService;

class SaleController extends Controller
{
    public function __construct(private SaleService $sales) {}

    public function index(SaleFilterRequest $request)
    {
        return view('sales.index', $this->sales->dashboard($request->validated()));
    }
}
