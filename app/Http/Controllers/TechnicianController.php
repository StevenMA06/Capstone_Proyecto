<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TechnicianController extends Controller
{
    private function assignments(Request $request)
    {
        $query = DB::table('pedidos_instalacion');
        if (! $request->user()->isManager()) {
            $employee = $request->user()->empleado_id;
            $employee ? $query->where('tecnico_id', $employee) : $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function index(Request $request)
    {
        return view('technician.index', ['records' => $this->assignments($request)->orderByDesc('pedido_id')->paginate(15)]);
    }

    public function show(Request $request, int $pedido)
    {
        $installation = $this->assignments($request)->where('pedido_id', $pedido)->first();
        abort_unless($installation, 404);

        return view('technician.show', [
            'installation' => $installation,
            'evidences' => DB::table('evidencias_instalacion')->where('pedido_id', $pedido)->orderByDesc('evidencia_id')->paginate(15),
        ]);
    }

    public function store(Request $request, int $pedido)
    {
        abort_unless($this->assignments($request)->where('pedido_id', $pedido)->exists(), 404);
        $data = $request->validate([
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ], ['evidence.required' => 'Selecciona una fotografía.', 'evidence.mimes' => 'Usa una imagen JPG, PNG o WEBP.', 'evidence.max' => 'La imagen no debe superar 5 MB.']);
        $path = null;
        try {
            DB::transaction(function () use ($request, $pedido, $data, &$path): void {
                abort_unless($this->assignments($request)->where('pedido_id', $pedido)->lockForUpdate()->first(), 404);
                $file = $request->file('evidence');
                $path = $file->store('installation-evidence', 'local');
                if (! $path) {
                    throw new \RuntimeException('No se pudo guardar la evidencia.');
                }
                DB::table('evidencias_instalacion')->insert([
                    'pedido_id' => $pedido, 'ruta_archivo' => $path,
                    'nombre_archivo' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getMimeType(), 'descripcion' => $data['descripcion'] ?? null,
                    'subido_por' => $request->user()->getKey(),
                ]);
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return redirect()->route('technician.show', $pedido)->with('status', 'Evidencia registrada correctamente.');
    }

    public function download(Request $request, int $pedido, int $evidence)
    {
        abort_unless($this->assignments($request)->where('pedido_id', $pedido)->exists(), 404);
        $record = DB::table('evidencias_instalacion')->where('pedido_id', $pedido)->where('evidencia_id', $evidence)->first();
        abort_unless($record && preg_match('/^installation-evidence\/[a-zA-Z0-9]+\.[a-zA-Z0-9]+$/D', $record->ruta_archivo), 404);
        abort_unless(Storage::disk('local')->exists($record->ruta_archivo), 404);

        return Storage::disk('local')->download($record->ruta_archivo, basename($record->ruta_archivo), ['X-Content-Type-Options' => 'nosniff']);
    }
}
