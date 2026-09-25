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
        'unidad_medida',
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
}
