<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportExportRequest;
use App\Services\ReportExportService;
use Illuminate\Http\Response;

class ReportExportController extends Controller
{
    public function store(ReportExportRequest $request, ReportExportService $service): Response
    {
        $file = $service->generate($request->validated());

        return response($file['content'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="'.$file['name'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

