<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $table = 'movimientos_inventario';

    protected $primaryKey = 'movimiento_id';

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tipo_movimiento',
        'equipo_id',
        'material_id',
        'pedido_id',
        'cantidad',
        'cantidad_anterior',
        'cantidad_posterior',
        'justificacion',
        'responsable_id',
        'fecha_movimiento',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'cantidad_anterior' => 'decimal:3',
            'cantidad_posterior' => 'decimal:3',
            'fecha_movimiento' => 'datetime',
        ];
    }

    /**
     * Relación con el equipo (si aplica)
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipo_id', 'equipo_id');
    }

    /**
     * Relación con el material (si aplica)
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id', 'material_id');
    }

    /**
     * Relación con el usuario responsable del movimiento
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id', 'usuario_id');
    }

    /**
     * Scope para filtrar por tipo de movimiento
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('tipo_movimiento', $type);
    }

    /**
     * Scope para filtrar por equipo
     */
    public function scopeForEquipment($query, int $equipmentId)
    {
        return $query->where('equipo_id', $equipmentId);
    }

    /**
     * Scope para filtrar por material
     */
    public function scopeForMaterial($query, int $materialId)
    {
        return $query->where('material_id', $materialId);
    }

    /**
     * Scope para ordenar por más reciente
     */
    public function scopeRecent($query)
    {
        return $query->orderByDesc('fecha_movimiento')->orderByDesc('movimiento_id');
    }

    /**
     * Determinar si el movimiento es de equipo o material
     */
    public function getItemTypeAttribute(): string
    {
        return $this->equipo_id ? 'Equipo' : 'Material';
    }

    /**
     * Obtener el nombre del ítem (equipo o material)
     */
    public function getItemNameAttribute(): ?string
    {
        if ($this->equipo_id && $this->equipment) {
            return $this->equipment->nombre;
        }
        
        if ($this->material_id && $this->material) {
            return $this->material->nombre;
        }
        
        return null;
    }

    /**
     * Obtener el código del ítem (equipo o material)
     */
    public function getItemCodeAttribute(): ?string
    {
        if ($this->equipo_id && $this->equipment) {
            return $this->equipment->codigo;
        }
        
        if ($this->material_id && $this->material) {
            return $this->material->codigo;
        }
        
        return null;
    }
}
