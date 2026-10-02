<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\User;
use App\Repositories\EquipmentRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\MaterialRepository;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private EquipmentRepository $equipments,
        private MaterialRepository $materials,
        private InventoryRepository $inventory
    ) {}

    /**
     * Registrar entrada de equipo con movimiento Kardex automático
     *
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function registerEquipment(array $data, User $user): Equipment
    {
        return DB::transaction(function () use ($data, $user) {
            // Generar código automático si no se proporcionó
            if (empty($data['codigo'])) {
                $data['codigo'] = $this->generateEquipmentCode();
            }

            // Crear equipo
            $equipment = $this->equipments->create(array_merge($data, [
                'creado_por' => $user->getKey(),
            ]));

            // Crear movimiento ENTRADA automático en el Kardex
            $this->inventory->createMovement([
                'tipo_movimiento' => 'ENTRADA',
                'equipo_id' => $equipment->equipo_id,
                'material_id' => null,
                'pedido_id' => null,
                'cantidad' => 1,
                'cantidad_anterior' => null,
                'cantidad_posterior' => null,
                'justificacion' => null,
                'responsable_id' => $user->getKey(),
                'fecha_movimiento' => now(),
                'observacion' => 'Registro inicial de equipo',
            ]);

            return $equipment;
        });
    }

    /**
     * Registrar entrada de material con stock inicial
     *
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function registerMaterial(array $data, User $user): Material
    {
        return DB::transaction(function () use ($data, $user) {
            // Generar código automático si no se proporcionó
            if (empty($data['codigo'])) {
                $data['codigo'] = $this->generateMaterialCode();
            }

            // Crear material
            $material = $this->materials->create(array_merge($data, [
                'creado_por' => $user->getKey(),
            ]));

            // Si tiene stock inicial, crear movimiento ENTRADA
            if ($material->stock_actual > 0) {
                $this->inventory->createMovement([
                    'tipo_movimiento' => 'ENTRADA',
                    'equipo_id' => null,
                    'material_id' => $material->material_id,
                    'pedido_id' => null,
                    'cantidad' => $material->stock_actual,
                    'cantidad_anterior' => 0,
                    'cantidad_posterior' => $material->stock_actual,
                    'justificacion' => null,
                    'responsable_id' => $user->getKey(),
                    'fecha_movimiento' => now(),
                    'observacion' => 'Stock inicial del material',
                ]);
            }

            return $material;
        });
    }

    /**
     * Registrar ingreso adicional de material
     *
     * @throws \Exception
     */
    public function addMaterialStock(Material $material, float $quantity, User $user, ?string $observacion = null): Material
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a cero');
        }

        return DB::transaction(function () use ($material, $quantity, $user, $observacion) {
            $stockAnterior = $material->stock_actual;

            // Incrementar stock
            $material = $this->materials->incrementStock($material, $quantity);

            // Crear movimiento ENTRADA en Kardex
            $this->inventory->createMovement([
                'tipo_movimiento' => 'ENTRADA',
                'equipo_id' => null,
                'material_id' => $material->material_id,
                'pedido_id' => null,
                'cantidad' => $quantity,
                'cantidad_anterior' => $stockAnterior,
                'cantidad_posterior' => $material->stock_actual,
                'justificacion' => null,
                'responsable_id' => $user->getKey(),
                'fecha_movimiento' => now(),
                'observacion' => $observacion ?? 'Ingreso de material al almacén',
            ]);

            return $material;
        });
    }

    /**
     * Registrar salida de material
     *
     * @throws \Exception
     */
    public function removeMaterialStock(Material $material, float $quantity, User $user, ?string $observacion = null): Material
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a cero');
        }

        if (!$material->hasSufficientStock($quantity)) {
            throw new \InvalidArgumentException(
                "Stock insuficiente. Disponible: {$material->stock_actual}, solicitado: {$quantity}"
            );
        }

        return DB::transaction(function () use ($material, $quantity, $user, $observacion) {
            $stockAnterior = $material->stock_actual;

            // Decrementar stock
            $material = $this->materials->decrementStock($material, $quantity);

            // Crear movimiento SALIDA en Kardex
            $this->inventory->createMovement([
                'tipo_movimiento' => 'SALIDA',
                'equipo_id' => null,
                'material_id' => $material->material_id,
                'pedido_id' => null,
                'cantidad' => $quantity,
                'cantidad_anterior' => $stockAnterior,
                'cantidad_posterior' => $material->stock_actual,
                'justificacion' => null,
                'responsable_id' => $user->getKey(),
                'fecha_movimiento' => now(),
                'observacion' => $observacion ?? 'Salida de material del almacén',
            ]);

            return $material;
        });
    }

    /**
     * Registrar ajuste manual de material (requiere justificación)
     *
     * @throws \Exception
     */
    public function adjustMaterialStock(
        Material $material,
        float $newStock,
        string $justificacion,
        User $user,
        ?string $observacion = null
    ): Material {
        if ($newStock < 0) {
            throw new \InvalidArgumentException('El stock no puede ser negativo');
        }

        if (trim($justificacion) === '') {
            throw new \InvalidArgumentException('El ajuste manual requiere justificación obligatoria');
        }

        return DB::transaction(function () use ($material, $newStock, $justificacion, $user, $observacion) {
            $stockAnterior = $material->stock_actual;
            $diferencia = $newStock - $stockAnterior;

            // Actualizar stock directamente
            $material->stock_actual = $newStock;
            $material->save();
            $material = $material->refresh();

            // Crear movimiento AJUSTE en Kardex
            $this->inventory->createMovement([
                'tipo_movimiento' => 'AJUSTE',
                'equipo_id' => null,
                'material_id' => $material->material_id,
                'pedido_id' => null,
                'cantidad' => abs($diferencia),
                'cantidad_anterior' => $stockAnterior,
                'cantidad_posterior' => $material->stock_actual,
                'justificacion' => $justificacion,
                'responsable_id' => $user->getKey(),
                'fecha_movimiento' => now(),
                'observacion' => $observacion ?? 'Ajuste manual de stock',
            ]);

            return $material;
        });
    }

    /**
     * Importación masiva de equipos con generación automática de Kardex
     *
     * @param array<int, array<string, mixed>> $validRows
     * @throws \Exception
     */
    public function importEquipments(array $validRows, User $user): int
    {
        return DB::transaction(function () use ($validRows, $user) {
            $imported = 0;

            foreach ($validRows as $row) {
                $data = $row['data'];
                
                // Generar código automático si está vacío
                if (empty($data['codigo'])) {
                    $data['codigo'] = $this->generateEquipmentCode();
                }

                // Crear equipo
                $equipment = $this->equipments->create(array_merge($data, [
                    'creado_por' => $user->getKey(),
                ]));

                // Crear movimiento ENTRADA para cada equipo
                $this->inventory->createMovement([
                    'tipo_movimiento' => 'ENTRADA',
                    'equipo_id' => $equipment->equipo_id,
                    'material_id' => null,
                    'pedido_id' => null,
                    'cantidad' => 1,
                    'cantidad_anterior' => null,
                    'cantidad_posterior' => null,
                    'justificacion' => null,
                    'responsable_id' => $user->getKey(),
                    'fecha_movimiento' => now(),
                    'observacion' => "Importación masiva - Fila {$row['row_number']}",
                ]);

                $imported++;
            }

            return $imported;
        });
    }

    /**
     * Actualizar equipo (sin generar movimiento Kardex)
     *
     * @param array<string, mixed> $data
     */
    public function updateEquipment(Equipment $equipment, array $data): Equipment
    {
        // Validar que no esté asignado si se intenta cambiar estado crítico
        if (isset($data['estado']) && $equipment->estado === 'ASIGNADO' && $data['estado'] !== 'ASIGNADO') {
            throw new \InvalidArgumentException('No se puede cambiar el estado de un equipo asignado');
        }

        return $this->equipments->update($equipment, $data);
    }

    /**
     * Actualizar material (sin modificar stock)
     *
     * @param array<string, mixed> $data
     */
    public function updateMaterial(Material $material, array $data): Material
    {
        return $this->materials->update($material, $data);
    }

    /**
     * Inhabilitar equipo
     */
    public function deactivateEquipment(Equipment $equipment): Equipment
    {
        if ($equipment->estado === 'ASIGNADO') {
            throw new \InvalidArgumentException('No se puede inhabilitar un equipo asignado');
        }

        return $this->equipments->deactivate($equipment);
    }

    /**
     * Inhabilitar material
     */
    public function deactivateMaterial(Material $material): Material
    {
        return $this->materials->deactivate($material);
    }

    /**
     * Generar código automático para equipos
     */
    private function generateEquipmentCode(): string
    {
        $lastEquipment = Equipment::orderBy('equipo_id', 'desc')->first();
        
        if (!$lastEquipment) {
            return 'EQ-0001';
        }

        // Extraer el número del último código
        $lastCode = $lastEquipment->codigo;
        
        // Si el código sigue el formato EQ-XXXX, incrementar
        if (preg_match('/^EQ-(\d+)$/', $lastCode, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
            return 'EQ-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        // Si no sigue el formato, contar total de equipos + 1
        $count = Equipment::count();
        return 'EQ-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generar código automático para materiales
     */
    private function generateMaterialCode(): string
    {
        $lastMaterial = Material::orderBy('material_id', 'desc')->first();
        
        if (!$lastMaterial) {
            return 'MAT-0001';
        }

        // Extraer el número del último código
        $lastCode = $lastMaterial->codigo;
        
        // Si el código sigue el formato MAT-XXXX, incrementar
        if (preg_match('/^MAT-(\d+)$/', $lastCode, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
            return 'MAT-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        // Si no sigue el formato, contar total de materiales + 1
        $count = Material::count();
        return 'MAT-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
