<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Requests\StockEntryRequest;
use App\Http\Requests\StockExitRequest;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Material;
use App\Repositories\InventoryRepository;
use App\Repositories\MaterialRepository;
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

class MaterialController extends Controller
{
    public function __construct(
        private InventoryService $inventory,
        private MaterialRepository $materials,
        private InventoryRepository $kardex
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'estado' => ['nullable', Rule::in(['ACTIVO', 'INACTIVO'])],
            'low_stock' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $materials = $this->materials->search(
            $filters['search'] ?? '',
            $filters['estado'] ?? null,
            $filters['low_stock'] ?? null
        );
        $materialIds = $materials->getCollection()->pluck('material_id');
        $reserved = DB::table('pedido_materiales as pm')->join('pedidos_instalacion as p', 'p.pedido_id', '=', 'pm.pedido_id')
            ->whereIn('pm.material_id', $materialIds)->whereIn('p.estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'])
            ->groupBy('pm.material_id')->select('pm.material_id')
            ->selectRaw('SUM(pm.cantidad_asignada - pm.cantidad_utilizada - pm.cantidad_devuelta) as reservado')
            ->pluck('reservado', 'material_id');
        foreach ($materials as $material) {
            $reservedQuantity = (float) ($reserved[$material->material_id] ?? 0);
            $material->setAttribute('reservado_pedido', $reservedQuantity);
            $material->setAttribute('stock_libre', max(0, (float) $material->stock_actual - $reservedQuantity));
        }

        $statistics = $this->materials->countByStockCondition();

        return view('materials.index', compact('materials', 'statistics'));
    }

    public function create(): View
    {
        return view('materials.create', ['material' => new Material]);
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        try {
            $material = $this->inventory->registerMaterial(
                $request->validated(),
                $request->user()
            );
        } catch (UniqueConstraintViolationException $exception) {
            return back()->withInput()->withErrors([
                'codigo' => 'Este código ya está registrado.',
            ]);
        }

        $message = 'Material registrado correctamente.';
        if ($material->stock_actual > 0) {
            $message .= ' Se generó automáticamente el movimiento de entrada en el Kardex.';
        }

        return redirect()->route('materials.show', $material)->with('status', $message);
    }

    public function show(Material $material): View
    {
        $material->load('creator');
        $movements = (auth()->user()->hasPermission('kardex.view') ? $this->kardex->getMaterialMovements($material->material_id) : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15));

        return view('materials.show', compact('material', 'movements'));
    }

    public function edit(Material $material): View
    {
        return view('materials.create', compact('material'));
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        try {
            $this->inventory->updateMaterial($material, $request->validated());
        } catch (UniqueConstraintViolationException $exception) {
            return back()->withInput()->withErrors([
                'codigo' => 'Este código ya está registrado.',
            ]);
        }

        return redirect()->route('materials.show', $material)
            ->with('status', 'Material actualizado correctamente.');
    }

    public function deactivate(Material $material): RedirectResponse
    {
        $this->inventory->deactivateMaterial($material);

        return redirect()->route('materials.show', $material)
            ->with('status', 'Material inhabilitado correctamente.');
    }

    /**
     * Mostrar formulario de ingreso de stock
     */
    public function entryForm(Material $material): View
    {
        return view('materials.entry', compact('material'));
    }

    /**
     * Procesar ingreso de stock
     */
    public function storeEntry(StockEntryRequest $request, Material $material): RedirectResponse
    {
        try {
            $this->inventory->addMaterialStock(
                $material,
                $request->input('cantidad'),
                $request->user(),
                $request->input('observacion')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['cantidad' => $e->getMessage()]);
        }

        return redirect()->route('materials.show', $material)
            ->with('status', 'Ingreso de stock registrado correctamente. El Kardex fue actualizado automáticamente.');
    }

    /**
     * Mostrar formulario de salida de stock
     */
    public function exitForm(Material $material): View
    {
        return view('materials.exit', compact('material'));
    }

    /**
     * Procesar salida de stock
     */
    public function storeExit(StockExitRequest $request, Material $material): RedirectResponse
    {
        try {
            $this->inventory->removeMaterialStock(
                $material,
                $request->input('cantidad'),
                $request->user(),
                $request->input('observacion')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['cantidad' => $e->getMessage()]);
        }

        return redirect()->route('materials.show', $material)
            ->with('status', 'Salida de stock registrada correctamente. El Kardex fue actualizado automáticamente.');
    }

    /**
     * Mostrar formulario de ajuste de stock
     */
    public function adjustmentForm(Material $material): View
    {
        return view('materials.adjustment', compact('material'));
    }

    /**
     * Procesar ajuste de stock
     */
    public function storeAdjustment(StockAdjustmentRequest $request, Material $material): RedirectResponse
    {
        try {
            $this->inventory->adjustMaterialStock(
                $material,
                $request->input('nuevo_stock'),
                $request->input('justificacion'),
                $request->user(),
                $request->input('observacion')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['nuevo_stock' => $e->getMessage()]);
        }

        return redirect()->route('materials.show', $material)
            ->with('status', 'Ajuste de stock registrado correctamente. El Kardex fue actualizado automáticamente.');
    }

    /**
     * Ver Kardex del material
     */
    public function kardex(Material $material): RedirectResponse
    {
        return redirect()->route('kardex.index', ['material_id' => $material->material_id]);
    }

    /**
     * Mostrar formulario de importación
     */
    public function importForm(): View
    {
        return view('materials.import');
    }

    /**
     * Descargar plantilla Excel con materiales existentes
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados
        $headers = [
            'codigo',
            'nombre',
            'descripcion',
            'unidad_base',
            'unidad_empaque',
            'factor_conversion',
            'inventario_en_empaque',
            'stock_actual',
            'stock_minimo',
            'cantidad_ingreso',
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Obtener todos los materiales activos
        $materials = $this->materials->getAllForExport();

        // Llenar con datos existentes
        $row = 2;
        foreach ($materials as $material) {
            $sheet->setCellValue('A' . $row, $material->codigo);
            $sheet->setCellValue('B' . $row, $material->nombre);
            $sheet->setCellValue('C' . $row, $material->descripcion);
            $sheet->setCellValue('D' . $row, $material->unidad_base);
            $sheet->setCellValue('E' . $row, $material->unidad_empaque);
            $sheet->setCellValue('F' . $row, $material->factor_conversion);
            $sheet->setCellValue('G' . $row, $material->inventario_en_empaque ? 'SI' : 'NO');
            $sheet->setCellValue('H' . $row, $material->stock_actual);
            $sheet->setCellValue('I' . $row, $material->stock_minimo);
            $sheet->setCellValue('J' . $row, ''); // cantidad_ingreso vacío para llenar
            $row++;
        }

        // Agregar filas vacías para nuevos materiales (5 filas)
        $sheet->setCellValue('A' . $row, '');
        $sheet->setCellValue('B' . $row, '');
        $sheet->setCellValue('C' . $row, '');
        $sheet->setCellValue('D' . $row, 'Unidad');
        $sheet->setCellValue('E' . $row, '');
        $sheet->setCellValue('F' . $row, '');
        $sheet->setCellValue('G' . $row, 'NO');
        $sheet->setCellValue('H' . $row, '0');
        $sheet->setCellValue('I' . $row, '0');
        $sheet->setCellValue('J' . $row, '0');

        // Agregar hoja de instrucciones
        $instructionsSheet = $spreadsheet->createSheet(1);
        $instructionsSheet->setTitle('Instrucciones');
        $instructionsSheet->setCellValue('A1', 'INSTRUCCIONES DE USO');
        $instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        
        $instructions = [
            '',
            'MATERIALES EXISTENTES:',
            '• Las primeras filas contienen los materiales ya registrados en el sistema.',
            '• Para agregar stock a un material existente, llena la columna "cantidad_ingreso".',
            '• NO modifiques el código de materiales existentes.',
            '• Puedes actualizar descripción, unidades y stock mínimo.',
            '',
            'CREAR NUEVOS MATERIALES:',
            '• Usa las filas vacías al final de la lista.',
            '• El código es OBLIGATORIO y debe ser único.',
            '• El nombre y unidad_base son OBLIGATORIOS.',
            '• Si usas unidad_empaque, debes especificar el factor_conversion (mayor a 0).',
            '• inventario_en_empaque: SI o NO (indica si el stock se guarda en empaques).',
            '• cantidad_ingreso: cantidad inicial de stock para el material nuevo.',
            '',
            'COLUMNAS:',
            '• codigo: Identificador único (ej: MAT-001)',
            '• nombre: Nombre descriptivo del material',
            '• descripcion: Detalles adicionales (opcional)',
            '• unidad_base: Unidad principal (ej: metros, kilogramos, unidades)',
            '• unidad_empaque: Unidad de empaque (ej: rollo, caja) - opcional',
            '• factor_conversion: Cuántas unidades base hay en un empaque (ej: 100)',
            '• inventario_en_empaque: SI si guardas stock en empaques, NO si en unidad base',
            '• stock_actual: Solo lectura para existentes',
            '• stock_minimo: Cantidad mínima recomendada',
            '• cantidad_ingreso: Cantidad a ingresar (para existentes o stock inicial de nuevos)',
        ];

        $row = 1;
        foreach ($instructions as $instruction) {
            $instructionsSheet->setCellValue('A' . $row, $instruction);
            $instructionsSheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }
        $instructionsSheet->getColumnDimension('A')->setWidth(100);

        // Activar la hoja de datos
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'materiales_' . now()->format('Y-m-d_His') . '.xlsx', [
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

                // Solo procesar filas que tengan código Y (nombre O cantidad_ingreso > 0)
                $codigo = trim($rowData[0] ?? '');
                $nombre = trim($rowData[1] ?? '');
                $cantidadIngreso = !empty($rowData[9]) ? (float)$rowData[9] : 0;

                if (empty($codigo) || (empty($nombre) && $cantidadIngreso <= 0)) {
                    continue;
                }

                $rows[] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'descripcion' => !empty($rowData[2]) ? trim($rowData[2]) : null,
                    'unidad_base' => !empty($rowData[3]) ? trim($rowData[3]) : null,
                    'unidad_empaque' => !empty($rowData[4]) ? trim($rowData[4]) : null,
                    'factor_conversion' => !empty($rowData[5]) ? (float)$rowData[5] : null,
                    'inventario_en_empaque' => !empty($rowData[6]) ? trim($rowData[6]) : 'NO',
                    'stock_minimo' => !empty($rowData[8]) ? (float)$rowData[8] : 0,
                    'cantidad_ingreso' => $cantidadIngreso,
                ];
            }

            if (empty($rows)) {
                return back()->withErrors(['archivo' => 'El archivo no contiene datos válidos para importar.']);
            }

            // Validar filas
            $validation = $this->materials->validateImportRows($rows);

            // Guardar resultados en sesión para confirmar
            session([
                'material_import_validation' => $validation,
                'material_import_file_name' => $file->getClientOriginalName(),
            ]);

            return view('materials.import-preview', [
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
        $validation = session('material_import_validation');

        if (!$validation) {
            return redirect()->route('materials.import')
                ->withErrors(['error' => 'No hay datos de importación para confirmar.']);
        }

        try {
            $result = $this->inventory->importMaterials(
                $validation['valid'],
                $request->user()
            );

            session()->forget(['material_import_validation', 'material_import_file_name']);

            $message = "Importación completada: {$result['created']} materiales creados";
            if ($result['updated'] > 0) {
                $message .= ", {$result['updated']} actualizados con nuevo stock";
            }
            $message .= ". Los movimientos de kardex fueron generados automáticamente.";

            return redirect()->route('materials.index')->with('status', $message);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al importar: ' . $e->getMessage()]);
        }
    }

    /**
     * Cancelar importación
     */
    public function cancelImport(): RedirectResponse
    {
        session()->forget(['material_import_validation', 'material_import_file_name']);

        return redirect()->route('materials.import')
            ->with('status', 'Importación cancelada.');
    }
}
