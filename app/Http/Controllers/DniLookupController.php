<?php

namespace App\Http\Controllers;

use App\Services\DniLookupService;
use Illuminate\Http\Request;
use RuntimeException;

class DniLookupController extends Controller
{
    public function __invoke(Request $request, DniLookupService $service)
    {
        $module = $request->route('context');
        abort_unless(in_array($module, ['clients', 'employees'], true)
            && ($request->user()->hasPermission($module.'.create') || $request->user()->hasPermission($module.'.update')),
            403, 'No tienes permiso para consultar DNI en este formulario.');

        $data = $request->validate(['dni' => ['required', 'string', 'regex:/^[0-9]{8}$/']], [
            'dni.required' => 'Ingresa el DNI para consultar.',
            'dni.string' => 'El DNI debe contener exactamente 8 dígitos numéricos.',
            'dni.regex' => 'El DNI debe contener exactamente 8 dígitos numéricos.',
        ]);

        try {
            return response()->json(['data' => $service->lookup($data['dni'])])
                ->header('Cache-Control', 'no-store, private');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getCode())
                ->header('Cache-Control', 'no-store, private');
        }
    }
}
