<?php

namespace App\Repositories;

use App\Models\Material;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MaterialRepository
{
    /**
     * Crear un material
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Material
    {
        return Material::create($attributes);
    }

    /**
     * Buscar materiales con filtros
     */
    public function search(string $search = '', ?string $estado = null, ?bool $lowStock = null): LengthAwarePaginator
    {
        $query = Material::query()->with('creator');

        // Búsqueda por código o nombre
        if ($search !== '') {
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $query->where(function ($q) use ($pattern) {
                $q->whereRaw("codigo LIKE ? ESCAPE '!'", [$pattern])
                  ->orWhereRaw("nombre LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        // Filtro por estado
        if ($estado) {
            $query->where('estado', $estado);
        }

        // Filtro por stock bajo
        if ($lowStock) {
            $query->whereColumn('stock_actual', '<=', 'stock_minimo');
        }

        return $query->orderBy('nombre')
            ->orderBy('material_id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Actualizar material (sin modificar stock directamente)
     *
     * @param array<string, mixed> $attributes
     */
    public function update(Material $material, array $attributes): Material
    {
        // Nunca permitir actualización directa de stock_actual desde este método
        unset($attributes['stock_actual']);

        $material->fill($attributes)->save();

        return $material->refresh();
    }

    /**
     * Inhabilitar material (no borrado físico)
     */
    public function deactivate(Material $material): Material
    {
        Material::whereKey($material->getKey())
            ->where('estado', 'ACTIVO')
            ->update(['estado' => 'INACTIVO']);

        return $material->refresh();
    }

    /**
     * Incrementar stock (usado desde el servicio de inventario)
     */
    public function incrementStock(Material $material, float $quantity): Material
    {
        $material->increment('stock_actual', $quantity);

        return $material->refresh();
    }

    /**
     * Decrementar stock (usado desde el servicio de inventario)
     */
    public function decrementStock(Material $material, float $quantity): Material
    {
        $material->decrement('stock_actual', $quantity);

        return $material->refresh();
    }

    /**
     * Verificar si el código ya existe
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = Material::where('codigo', $code);

        if ($excludeId) {
            $query->where('material_id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Obtener materiales con stock bajo
     */
    public function getLowStock(): Collection
    {
        return Material::where('estado', 'ACTIVO')
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Obtener materiales sin stock
     */
    public function getOutOfStock(): Collection
    {
        return Material::where('estado', 'ACTIVO')
            ->where('stock_actual', '<=', 0)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Contar materiales por condición de stock
     *
     * @return array<string, int>
     */
    public function countByStockCondition(): array
    {
        $total = Material::where('estado', 'ACTIVO')->count();
        $lowStock = Material::where('estado', 'ACTIVO')
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->count();
        $outOfStock = Material::where('estado', 'ACTIVO')
            ->where('stock_actual', '<=', 0)
            ->count();

        return [
            'total' => $total,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'ok' => $total - $lowStock,
        ];
    }
}
