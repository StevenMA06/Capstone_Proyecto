<?php

namespace App\Http\Controllers;

use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request, ReportExportService $reports): View
    {
        $manager = $request->user()->isManager();
        $modules = $reports->modules($manager);
        $module = $request->query('modulo');
        $module = is_string($module) && isset($modules[$module]) ? $module : array_key_first($modules);
        $available = $reports->reportsForModule($module);
        $type = $request->query('tipo');
        $type = is_string($type) && isset($available[$type]) ? $type : array_key_first($available);
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'required_with:hasta', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'required_with:desde', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
        $selectedFrom = $validated['desde'] ?? null;
        $selectedTo = $validated['hasta'] ?? null;
        $from = $selectedFrom ?? now('America/Lima')->startOfMonth()->toDateString();
        $to = $selectedTo ?? now('America/Lima')->toDateString();

        if ($from > $to) throw \Illuminate\Validation\ValidationException::withMessages(['hasta'=>'La fecha final debe ser igual o posterior a la inicial.']);

        return view('analytics.reports', [
            'modules' => $modules,
            'selectedModule' => $module,
            'availableReports' => $available,
            'selectedType' => $type,
            'from' => $from,
            'to' => $to,
            'selectedFrom' => $selectedFrom,
            'selectedTo' => $selectedTo,
            'report' => $reports->report($type, $from, $to, (int) ($validated['page'] ?? 1)),
            'manager' => $manager,
        ]);
    }
}

