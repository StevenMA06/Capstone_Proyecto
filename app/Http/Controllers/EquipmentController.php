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
        $movements = (auth()->user()->hasPermission('kardex.view') ? $this->kardex->getEquipmentMovements($equipment->equipo_id) : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15));

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
     * Descargar plantilla Excel mejorada
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Equipos');

        // Estilos para encabezados
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '047857'], // Verde oscuro
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        $exampleStyle = [
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D1FAE5'], // Verde claro
            ],
        ];

        // Encabezados
        $headers = [
            'A' => ['text' => 'Código', 'width' => 15, 'comment' => 'Opcional. Si se deja vacío, se genera automáticamente (ej: EQ-001)'],
            'B' => ['text' => 'Número de Serie *', 'width' => 20, 'comment' => 'Obligatorio. Debe ser único'],
            'C' => ['text' => 'Nombre *', 'width' => 25, 'comment' => 'Obligatorio. Nombre descriptivo del equipo'],
            'D' => ['text' => 'Tipo *', 'width' => 18, 'comment' => 'Obligatorio. Ej: Router, Switch, ONT, Cable, etc.'],
            'E' => ['text' => 'Marca', 'width' => 15, 'comment' => 'Opcional. Ej: TP-Link, Cisco, Huawei'],
            'F' => ['text' => 'Modelo', 'width' => 18, 'comment' => 'Opcional. Ej: Archer C6, WS-C2960'],
            'G' => ['text' => 'Condición', 'width' => 15, 'comment' => 'NUEVO, BUENO, REGULAR, MALO o MALOGRADO. Por defecto: BUENO'],
            'H' => ['text' => 'Estado', 'width' => 15, 'comment' => 'DISPONIBLE, ASIGNADO, MANTENIMIENTO o INACTIVO. Por defecto: DISPONIBLE'],
            'I' => ['text' => 'Observaciones', 'width' => 30, 'comment' => 'Opcional. Notas adicionales'],
        ];

        // Aplicar encabezados
        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . '1', $header['text']);
            $sheet->getColumnDimension($col)->setWidth($header['width']);
            $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            
            // Agregar comentarios
            $sheet->getComment($col . '1')->getText()->createTextRun($header['comment']);
            $sheet->getComment($col . '1')->setWidth('300px');
            $sheet->getComment($col . '1')->setHeight('80px');
        }

        // Ajustar altura del encabezado
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Ejemplos (3 filas)
        $examples = [
            ['', 'SN123456789', 'Router WiFi AC1200', 'Router', 'TP-Link', 'Archer C6', 'NUEVO', 'DISPONIBLE', 'Para instalación residencial'],
            ['EQ-CUSTOM-01', 'SN987654321', 'Switch Gigabit 24 puertos', 'Switch', 'Cisco', 'WS-C2960X', 'BUENO', 'DISPONIBLE', 'Rack principal'],
            ['', 'ONT-2024-001', 'ONT GPON HG8145V5', 'ONT', 'Huawei', 'HG8145V5', 'NUEVO', 'DISPONIBLE', 'Cliente nuevo - Sector A'],
        ];

        $row = 2;
        foreach ($examples as $example) {
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($exampleStyle);
            $row++;
        }

        // Validación de datos para condición
        $condicionValidation = $sheet->getCell('G5')->getDataValidation();
        $condicionValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $condicionValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
        $condicionValidation->setAllowBlank(false);
        $condicionValidation->setShowInputMessage(true);
        $condicionValidation->setShowErrorMessage(true);
        $condicionValidation->setShowDropDown(true);
        $condicionValidation->setErrorTitle('Valor inválido');
        $condicionValidation->setError('Debe seleccionar: NUEVO, BUENO, REGULAR, MALO o MALOGRADO');
        $condicionValidation->setPromptTitle('Condición del equipo');
        $condicionValidation->setPrompt('Seleccione la condición física');
        $condicionValidation->setFormula1('"NUEVO,BUENO,REGULAR,MALO,MALOGRADO"');

        // Validación de datos para estado
        $estadoValidation = $sheet->getCell('H5')->getDataValidation();
        $estadoValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $estadoValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
        $estadoValidation->setAllowBlank(false);
        $estadoValidation->setShowInputMessage(true);
        $estadoValidation->setShowErrorMessage(true);
        $estadoValidation->setShowDropDown(true);
        $estadoValidation->setErrorTitle('Valor inválido');
        $estadoValidation->setError('Debe seleccionar: DISPONIBLE, ASIGNADO, MANTENIMIENTO o INACTIVO');
        $estadoValidation->setPromptTitle('Estado del equipo');
        $estadoValidation->setPrompt('Seleccione el estado operativo');
        $estadoValidation->setFormula1('"DISPONIBLE,ASIGNADO,MANTENIMIENTO,INACTIVO"');

        // Copiar validaciones a más filas (hasta la fila 100)
        for ($i = 5; $i <= 100; $i++) {
            $sheet->getCell('G' . $i)->setDataValidation(clone $condicionValidation);
            $sheet->getCell('H' . $i)->setDataValidation(clone $estadoValidation);
        }

        // Hoja de instrucciones
        $instructionsSheet = $spreadsheet->createSheet(1);
        $instructionsSheet->setTitle('📖 Instrucciones');
        
        $titleStyle = [
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '047857'],
            ],
        ];

        $sectionStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => '059669'],
            ],
        ];

        $instructionsSheet->setCellValue('A1', '📦 GUÍA DE IMPORTACIÓN DE EQUIPOS');
        $instructionsSheet->getStyle('A1')->applyFromArray($titleStyle);
        $instructionsSheet->getRowDimension(1)->setRowHeight(30);

        $instructions = [
            ['', ''],
            ['✅ PASO 1: Preparar los datos', ''],
            ['', '• Usa la pestaña "Equipos" para ingresar los datos'],
            ['', '• Las filas 2-4 contienen ejemplos que puedes eliminar o modificar'],
            ['', '• Los campos marcados con * son obligatorios'],
            ['', ''],
            ['📝 PASO 2: Completar columnas', ''],
            ['', 'Código: Opcional. Si lo dejas vacío, se generará automáticamente (EQ-001, EQ-002...)'],
            ['', 'Número de Serie: OBLIGATORIO. Debe ser único. Ej: SN123456789'],
            ['', 'Nombre: OBLIGATORIO. Descripción del equipo. Ej: Router WiFi AC1200'],
            ['', 'Tipo: OBLIGATORIO. Categoría del equipo. Ej: Router, Switch, ONT, Módem'],
            ['', 'Marca: Opcional. Fabricante. Ej: TP-Link, Cisco, Huawei'],
            ['', 'Modelo: Opcional. Modelo específico. Ej: Archer C6, WS-C2960'],
            ['', 'Condición: Usa el menú desplegable. Valores: NUEVO, BUENO, REGULAR, MALO, MALOGRADO'],
            ['', 'Estado: Usa el menú desplegable. Valores: DISPONIBLE, ASIGNADO, MANTENIMIENTO, INACTIVO'],
            ['', 'Observaciones: Opcional. Cualquier nota relevante'],
            ['', ''],
            ['💾 PASO 3: Guardar y cargar', ''],
            ['', '• Guarda el archivo en formato .xlsx'],
            ['', '• Ve a la sección "Importar Equipos" en el sistema'],
            ['', '• Carga el archivo y revisa la vista previa'],
            ['', '• Confirma la importación'],
            ['', ''],
            ['⚠️ IMPORTANTE:', ''],
            ['', '• Cada equipo importado generará automáticamente su entrada en el Kardex'],
            ['', '• Los códigos duplicados serán rechazados'],
            ['', '• Los números de serie deben ser únicos'],
            ['', '• Puedes importar máximo 500 equipos por archivo'],
        ];

        $row = 2;
        foreach ($instructions as $instruction) {
            $instructionsSheet->setCellValue('A' . $row, $instruction[0]);
            if (!empty($instruction[1])) {
                $instructionsSheet->setCellValue('B' . $row, $instruction[1]);
            }
            
            if (!empty($instruction[0]) && (str_starts_with($instruction[0], '✅') || str_starts_with($instruction[0], '📝') || str_starts_with($instruction[0], '💾') || str_starts_with($instruction[0], '⚠️'))) {
                $instructionsSheet->getStyle('A' . $row)->applyFromArray($sectionStyle);
            }
            
            $instructionsSheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        $instructionsSheet->getColumnDimension('A')->setWidth(5);
        $instructionsSheet->getColumnDimension('B')->setWidth(90);

        // Volver a la hoja principal
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'plantilla_equipos_' . now()->format('Y-m-d') . '.xlsx', [
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
