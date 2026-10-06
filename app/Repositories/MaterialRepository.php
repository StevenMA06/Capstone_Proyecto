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

    /**
     * Obtener todos los materiales activos para exportación
     */
    public function getAllForExport(): Collection
    {
        return Material::where('estado', 'ACTIVO')
            ->orderBy('nombre')
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Validar filas de importación
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array{valid: array<int, array{row_number: int, data: array<string, mixed>}>, invalid: array<int, array{row_number: int, data: array<string, mixed>, errors: array<int, string>}>}
     */
    public function validateImportRows(array $rows): array
    {
        $valid = [];
        $invalid = [];
        $seenCodes = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 porque Excel empieza en 1 y tiene fila de encabezado
            $errors = [];

            // Validar código (requerido y único)
            if (empty($row['codigo'])) {
                $errors[] = 'El código es obligatorio.';
            } else {
                $codigo = $row['codigo'];
                
                // Verificar duplicados en el archivo
                if (isset($seenCodes[$codigo])) {
                    $errors[] = "Código duplicado en el archivo (fila {$seenCodes[$codigo]}).";
                } else {
                    $seenCodes[$codigo] = $rowNumber;
                }
            }

            // Validar nombre (requerido)
            if (empty($row['nombre'])) {
                $errors[] = 'El nombre es obligatorio.';
            }

            // Validar unidad base (requerido)
            if (empty($row['unidad_base'])) {
                $errors[] = 'La unidad base es obligatoria.';
            }

            // Validar factor de conversión si hay unidad de empaque
            if (!empty($row['unidad_empaque'])) {
                if (empty($row['factor_conversion']) || !is_numeric($row['factor_conversion']) || $row['factor_conversion'] <= 0) {
                    $errors[] = 'Si hay unidad de empaque, el factor de conversión debe ser mayor a 0.';
                }
            }

            // Validar cantidad ingreso (debe ser numérico y >= 0)
            if (!empty($row['cantidad_ingreso'])) {
                if (!is_numeric($row['cantidad_ingreso']) || $row['cantidad_ingreso'] < 0) {
                    $errors[] = 'La cantidad de ingreso debe ser un número mayor o igual a 0.';
                }
            }

            // Validar stock mínimo (debe ser numérico y >= 0)
            if (isset($row['stock_minimo']) && $row['stock_minimo'] !== '') {
                if (!is_numeric($row['stock_minimo']) || $row['stock_minimo'] < 0) {
                    $errors[] = 'El stock mínimo debe ser un número mayor o igual a 0.';
                }
            }

            // Validar inventario en empaque (si/no)
            if (!empty($row['inventario_en_empaque'])) {
                $val = strtolower(trim($row['inventario_en_empaque']));
                if (!in_array($val, ['si', 'sí', 'no', 's', 'n', 'yes', 'y'])) {
                    $errors[] = 'Inventario en empaque debe ser "SI" o "NO".';
                }
            }

            if (empty($errors)) {
                $valid[] = [
                    'row_number' => $rowNumber,
                    'data' => $row,
                ];
            } else {
                $invalid[] = [
                    'row_number' => $rowNumber,
                    'data' => $row,
                    'errors' => $errors,
                ];
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * Buscar material por código
     */
    public function findByCode(string $code): ?Material
    {
        return Material::where('codigo', $code)->first();
    }
}
