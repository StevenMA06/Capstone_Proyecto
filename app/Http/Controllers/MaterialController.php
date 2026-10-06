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
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
}
