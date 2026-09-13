<?php

namespace App\Http\Controllers;

use App\Repositories\AccessRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccessHistoryController extends Controller
{
    public function __invoke(Request $request, AccessRepository $access)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:60'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'result' => ['nullable', Rule::in(['EXITOSO', 'RECHAZADO'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return view('access.index', ['records' => $access->history($filters)]);
    }
}
