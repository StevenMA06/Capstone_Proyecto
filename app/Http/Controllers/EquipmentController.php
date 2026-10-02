<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Models\Equipment;
use App\Repositories\EquipmentRepository;
use App\Repositories\InventoryRepository;
use App\Services\InventoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentController extends Controller
{
    public function __construct(
        private InventoryService $inventory,
        private EquipmentRepository $equipments,
        private InventoryRepository $kardex
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'estado' => ['nullable', Rule::in(['DISPONIBLE', 'ASIGNADO', 'MANTENIMIENTO', 'INACTIVO'])],
            'condicion' => ['nullable', Rule::in(['NUEVO', 'BUENO', 'REGULAR', 'MALO', 'MALOGRADO'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $equipments = $this->equipments->search(
            $filters['search'] ?? '',
            $filters['estado'] ?? null,
            $filters['condicion'] ?? null
        );

        $statistics = $this->equipments->countByStatus();

        return view('equipments.index', compact('equipments', 'statistics'));
    }

    public function create(): View
    {
        return view('equipments.create', ['equipment' => new Equipment]);
    }

    public function store(StoreEquipmentRequest $request): RedirectResponse
    {
        try {
            $equipment = $this->inventory->registerEquipment(
                $request->validated(),
                $request->user()
            );
        } catch (UniqueConstraintViolationException $exception) {
            return back()->withInput()->withErrors([
                'codigo' => 'Este código ya está registrado.',
            ]);
        }

        return redirect()->route('equipments.show', $equipment)
            ->with('status', 'Equipo registrado correctamente. Se generó automáticamente el movimiento de entrada en el Kardex.');
    }

    public function show(Equipment $equipment): View
    {
        $equipment->load('creator');
        $movements = $this->kardex->getEquipmentMovements($equipment->equipo_id);

        return view('equipments.show', compact('equipment', 'movements'));
    }

    public function edit(Equipment $equipment): View
    {
        return view('equipments.create', compact('equipment'));
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): RedirectResponse
    {
        try {
            $this->inventory->updateEquipment($equipment, $request->validated());
        } catch (UniqueConstraintViolationException $exception) {
            return back()->withInput()->withErrors([
                'codigo' => 'Este código ya está registrado.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors([
                'estado' => $e->getMessage(),
            ]);
        }

        return redirect()->route('equipments.show', $equipment)
            ->with('status', 'Equipo actualizado correctamente.');
    }

    public function deactivate(Equipment $equipment): RedirectResponse
    {
        try {
            $this->inventory->deactivateEquipment($equipment);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('equipments.show', $equipment)
            ->with('status', 'Equipo inhabilitado correctamente.');
    }

    /**
     * Mostrar formulario de importación
     */
    public function importForm(): View
    {
        return view('equipments.import');
    }

    /**
     * Descargar plantilla Excel
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados
        $headers = [
            'codigo (opcional)',
            'numero_serie',
            'nombre',
            'tipo',
            'marca',
            'modelo',
            'condicion',
            'estado',
            'observaciones',
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Fila de ejemplo
        $sheet->setCellValue('A2', '');  // Vacío para mostrar que es opcional
        $sheet->setCellValue('B2', 'SN123456');
        $sheet->setCellValue('C2', 'Router TP-Link');
        $sheet->setCellValue('D2', 'Router');
        $sheet->setCellValue('E2', 'TP-Link');
        $sheet->setCellValue('F2', 'Archer C6');
        $sheet->setCellValue('G2', 'NUEVO');
        $sheet->setCellValue('H2', 'DISPONIBLE');
        $sheet->setCellValue('I2', 'Ejemplo de observación');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'plantilla_equipos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Procesar archivo de importación
     */
    public function processImport(Request $request): RedirectResponse|View
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
        ]);

        try {
            $file = $request->file('archivo');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = [];

            // Leer filas (empezar desde la fila 2, ignorando encabezados)
            foreach ($sheet->getRowIterator(2) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                // Ignorar filas completamente vacías
                if (empty(array_filter($rowData))) {
                    continue;
                }

                $rows[] = [
                    'codigo' => trim($rowData[0] ?? ''),
                    'numero_serie' => !empty($rowData[1]) ? trim($rowData[1]) : null,
                    'nombre' => trim($rowData[2] ?? ''),
                    'tipo' => trim($rowData[3] ?? ''),
                    'marca' => !empty($rowData[4]) ? trim($rowData[4]) : null,
                    'modelo' => !empty($rowData[5]) ? trim($rowData[5]) : null,
                    'condicion' => !empty($rowData[6]) ? strtoupper(trim($rowData[6])) : 'BUENO',
                    'estado' => !empty($rowData[7]) ? strtoupper(trim($rowData[7])) : 'DISPONIBLE',
                    'observaciones' => !empty($rowData[8]) ? trim($rowData[8]) : null,
                ];
            }

            if (empty($rows)) {
                return back()->withErrors(['archivo' => 'El archivo no contiene datos válidos.']);
            }

            // Validar filas
            $validation = $this->equipments->validateImportRows($rows);

            // Guardar resultados en sesión para confirmar
            session([
                'import_validation' => $validation,
                'import_file_name' => $file->getClientOriginalName(),
            ]);

            return view('equipments.import-preview', [
                'valid' => $validation['valid'],
                'invalid' => $validation['invalid'],
                'fileName' => $file->getClientOriginalName(),
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['archivo' => 'Error al procesar el archivo: ' . $e->getMessage()]);
        }
    }

    /**
     * Confirmar importación
     */
    public function confirmImport(Request $request): RedirectResponse
    {
        $validation = session('import_validation');

        if (!$validation) {
            return redirect()->route('equipments.import')
                ->withErrors(['error' => 'No hay datos de importación para confirmar.']);
        }

        try {
            $imported = $this->inventory->importEquipments(
                $validation['valid'],
                $request->user()
            );

            session()->forget(['import_validation', 'import_file_name']);

            return redirect()->route('equipments.index')
                ->with('status', "Se importaron {$imported} equipos correctamente. Se generaron los movimientos de entrada en el Kardex.");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al importar: ' . $e->getMessage()]);
        }
    }

    /**
     * Cancelar importación
     */
    public function cancelImport(): RedirectResponse
    {
        session()->forget(['import_validation', 'import_file_name']);

        return redirect()->route('equipments.import')
            ->with('status', 'Importación cancelada.');
    }

    /**
     * Ver Kardex del equipo
     */
    public function kardex(Equipment $equipment): RedirectResponse
    {
        return redirect()->route('kardex.index', ['equipo_id' => $equipment->equipo_id]);
    }
}
