<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $table = 'materiales';

    protected $primaryKey = 'material_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'unidad_base',
        'unidad_empaque',
        'factor_conversion',
        'inventario_en_empaque',
        'stock_actual',
        'stock_minimo',
        'estado',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'stock_actual' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'factor_conversion' => 'decimal:3',
            'inventario_en_empaque' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /**
     * Relación con el usuario que registró el material
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por', 'usuario_id');
    }

    /**
     * Relación con los movimientos de inventario del material
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'material_id', 'material_id');
    }

    /**
     * Verificar si el stock está en nivel mínimo o por debajo
     */
    public function isLowStock(): bool
    {
        return $this->stock_actual <= $this->stock_minimo;
    }

    /**
     * Verificar si hay stock suficiente para una cantidad solicitada
     */
    public function hasSufficientStock(float $quantity): bool
    {
        return $this->stock_actual >= $quantity;
    }

    /**
     * Verificar si el stock está agotado
     */
    public function isOutOfStock(): bool
    {
        return $this->stock_actual <= 0;
    }

    /**
     * Verificar si el material usa unidad de empaque
     */
    public function usesPackaging(): bool
    {
        return !empty($this->unidad_empaque) && $this->factor_conversion > 0 && $this->inventario_en_empaque;
    }

    /**
     * Obtener el stock en la unidad correcta (empaques o base)
     * Este es el stock REAL almacenado
     */
    public function getStockInCurrentUnit(): float
    {
        return $this->stock_actual;
    }

    /**
     * Obtener el stock en empaques
     * Si ya está en empaques, retorna directo
     * Si está en unidad base, convierte
     */
    public function getStockInPackages(): float
    {
        if ($this->inventario_en_empaque) {
            return $this->stock_actual; // Ya está en empaques
        }
        
        if ($this->usesPackaging()) {
            return $this->stock_actual / $this->factor_conversion; // Convertir
        }
        
        return $this->stock_actual;
    }

    /**
     * Obtener el stock en unidad base
     * Si ya está en base, retorna directo
     * Si está en empaques, convierte
     */
    public function getStockInBase(): float
    {
        if ($this->inventario_en_empaque && $this->factor_conversion > 0) {
            return $this->stock_actual * $this->factor_conversion; // Convertir
        }
        
        return $this->stock_actual; // Ya está en base
    }

    /**
     * Obtener el stock mínimo en empaques
     */
    public function getMinStockInPackages(): float
    {
        if ($this->inventario_en_empaque) {
            return $this->stock_minimo;
        }
        
        if ($this->usesPackaging()) {
            return $this->stock_minimo / $this->factor_conversion;
        }
        
        return $this->stock_minimo;
    }

    /**
     * Convertir cantidad de empaque a unidad base
     */
    public function convertPackagesToBase(float $packages): float
    {
        if (!$this->usesPackaging()) {
            return $packages;
        }

        return $packages * $this->factor_conversion;
    }

    /**
     * Convertir cantidad de unidad base a empaque
     */
    public function convertBaseToPackages(float $base): float
    {
        if (!$this->usesPackaging()) {
            return $base;
        }

        return $base / $this->factor_conversion;
    }

    /**
     * Formatear stock para mostrar
     */
    public function getFormattedStock(): string
    {
        if ($this->inventario_en_empaque) {
            // Stock guardado en empaques, mostrar ambos
            $packages = $this->stock_actual;
            $base = $this->getStockInBase();
            
            return number_format($packages, 2) . ' ' . $this->unidad_empaque . 
                   ' (' . number_format($base, 2) . ' ' . $this->unidad_base . ')';
        }
        
        // Stock guardado en unidad base, mostrar solo esa
        return number_format($this->stock_actual, 2) . ' ' . $this->unidad_base;
    }

    /**
     * Obtener la unidad en la que está almacenado el inventario
     */
    public function getInventoryUnit(): string
    {
        return $this->inventario_en_empaque ? $this->unidad_empaque : $this->unidad_base;
    }

    /**
     * Accessor para compatibilidad con código legacy que usa unidad_medida
     * Retorna la unidad en la que está almacenado el inventario
     */
    public function getUnidadMedidaAttribute(): string
    {
        return $this->getInventoryUnit();
    }
}
