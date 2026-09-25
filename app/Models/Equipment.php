<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipos';

    protected $primaryKey = 'equipo_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'codigo',
        'numero_serie',
        'nombre',
        'tipo',
        'marca',
        'modelo',
        'condicion',
        'estado',
        'observaciones',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /**
     * Relación con el usuario que registró el equipo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por', 'usuario_id');
    }

    /**
     * Relación con los movimientos de inventario del equipo
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'equipo_id', 'equipo_id');
    }

    /**
     * Verificar si el equipo está disponible para asignación
     */
    public function isAvailable(): bool
    {
        return $this->estado === 'DISPONIBLE' && 
               in_array($this->condicion, ['NUEVO', 'BUENO', 'REGULAR']);
    }

    /**
     * Verificar si el equipo puede ser editado
     */
    public function canBeEdited(): bool
    {
        return $this->estado !== 'ASIGNADO';
    }
}
