<?php

namespace App\Repositories;

use App\Models\Equipment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EquipmentRepository
{
    /**
     * Crear un equipo
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Equipment
    {
        return Equipment::create($attributes);
    }

    /**
     * Buscar equipos con filtros
     */
    public function search(string $search = '', ?string $estado = null, ?string $condicion = null): LengthAwarePaginator
    {
        $query = Equipment::query()->with('creator');

        // Búsqueda por código, nombre, tipo, número de serie
        if ($search !== '') {
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $query->where(function ($q) use ($pattern) {
                $q->whereRaw("codigo LIKE ? ESCAPE '!'", [$pattern])
                  ->orWhereRaw("numero_serie LIKE ? ESCAPE '!'", [$pattern])
                  ->orWhereRaw("nombre LIKE ? ESCAPE '!'", [$pattern])
                  ->orWhereRaw("tipo LIKE ? ESCAPE '!'", [$pattern])
                  ->orWhereRaw("marca LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        // Filtro por estado
        if ($estado) {
            $query->where('estado', $estado);
        }

        // Filtro por condición
        if ($condicion) {
            $query->where('condicion', $condicion);
        }

        return $query->orderBy('nombre')
            ->orderBy('equipo_id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Actualizar equipo
     *
     * @param array<string, mixed> $attributes
     */
    public function update(Equipment $equipment, array $attributes): Equipment
    {
        $equipment->fill($attributes)->save();

        return $equipment->refresh();
    }

    /**
     * Inhabilitar equipo (no borrado físico)
     */
    public function deactivate(Equipment $equipment): Equipment
    {
        Equipment::whereKey($equipment->getKey())
            ->where('estado', '!=', 'INACTIVO')
            ->update(['estado' => 'INACTIVO']);

        return $equipment->refresh();
    }

    /**
     * Verificar si el código ya existe
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = Equipment::where('codigo', $code);

        if ($excludeId) {
            $query->where('equipo_id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Verificar si el número de serie ya existe
     */
    public function serialExists(?string $serial, ?int $excludeId = null): bool
    {
        if (!$serial) {
            return false;
        }

        $query = Equipment::where('numero_serie', $serial);

        if ($excludeId) {
            $query->where('equipo_id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Obtener equipos disponibles
     */
    public function getAvailable(): Collection
    {
        return Equipment::where('estado', 'DISPONIBLE')
            ->whereIn('condicion', ['NUEVO', 'BUENO', 'REGULAR'])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Contar equipos por estado
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $counts = Equipment::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        return [
            'DISPONIBLE' => $counts['DISPONIBLE'] ?? 0,
            'ASIGNADO' => $counts['ASIGNADO'] ?? 0,
            'MANTENIMIENTO' => $counts['MANTENIMIENTO'] ?? 0,
            'INACTIVO' => $counts['INACTIVO'] ?? 0,
        ];
    }

    /**
     * Importación masiva: validar filas
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array{valid: array, invalid: array}
     */
    public function validateImportRows(array $rows): array
    {
        $valid = [];
        $invalid = [];
        $codesInFile = [];
        $serialsInFile = [];

        foreach ($rows as $index => $row) {
            $errors = [];
            $rowNumber = $index + 1;

            // Validar código
            if (empty($row['codigo'])) {
                $errors[] = 'Código es obligatorio';
            } elseif (strlen($row['codigo']) > 60) {
                $errors[] = 'Código no puede exceder 60 caracteres';
            } elseif ($this->codeExists($row['codigo'])) {
                $errors[] = 'Código ya existe en la base de datos';
            } elseif (in_array($row['codigo'], $codesInFile)) {
                $errors[] = 'Código duplicado en el archivo';
            } else {
                $codesInFile[] = $row['codigo'];
            }

            // Validar número de serie (opcional pero único)
            if (!empty($row['numero_serie'])) {
                if (strlen($row['numero_serie']) > 100) {
                    $errors[] = 'Número de serie no puede exceder 100 caracteres';
                } elseif ($this->serialExists($row['numero_serie'])) {
                    $errors[] = 'Número de serie ya existe en la base de datos';
                } elseif (in_array($row['numero_serie'], $serialsInFile)) {
                    $errors[] = 'Número de serie duplicado en el archivo';
                } else {
                    $serialsInFile[] = $row['numero_serie'];
                }
            }

            // Validar nombre
            if (empty($row['nombre'])) {
                $errors[] = 'Nombre es obligatorio';
            } elseif (strlen($row['nombre']) > 120) {
                $errors[] = 'Nombre no puede exceder 120 caracteres';
            }

            // Validar tipo
            if (empty($row['tipo'])) {
                $errors[] = 'Tipo es obligatorio';
            } elseif (strlen($row['tipo']) > 80) {
                $errors[] = 'Tipo no puede exceder 80 caracteres';
            }

            // Validar condición
            $validConditions = ['NUEVO', 'BUENO', 'REGULAR', 'MALO', 'MALOGRADO'];
            if (!empty($row['condicion']) && !in_array(strtoupper($row['condicion']), $validConditions)) {
                $errors[] = 'Condición debe ser: NUEVO, BUENO, REGULAR, MALO o MALOGRADO';
            }

            // Validar estado
            $validStates = ['DISPONIBLE', 'ASIGNADO', 'MANTENIMIENTO', 'INACTIVO'];
            if (!empty($row['estado']) && !in_array(strtoupper($row['estado']), $validStates)) {
                $errors[] = 'Estado debe ser: DISPONIBLE, ASIGNADO, MANTENIMIENTO o INACTIVO';
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

        return [
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }

    /**
     * Crear equipos en lote
     *
     * @param array<int, array<string, mixed>> $equipmentData
     * @return int Número de equipos creados
     */
    public function createBatch(array $equipmentData, int $userId): int
    {
        $created = 0;

        foreach ($equipmentData as $data) {
            $data['creado_por'] = $userId;
            $this->create($data);
            $created++;
        }

        return $created;
    }
}
