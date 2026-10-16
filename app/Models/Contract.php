<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;
    protected $table = 'contratos';

    protected $primaryKey = 'contrato_id';

    public $timestamps = false;

    protected $fillable = [
        'codigo', 'cliente_id', 'plan_id', 'zona_id', 'responsable_id',
        'fecha_inicio', 'duracion_meses', 'fecha_fin', 'precio_contratado',
        'direccion_servicio', 'observaciones', 'estado', 'pdf_contrato_ruta',
        'periodo_actual',
    ];

    /**
     * Cliente asociado al contrato
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'cliente_id', 'cliente_id');
    }

    /**
     * Plan de servicio contratado
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServicePlan::class, 'plan_id', 'plan_id');
    }

    /**
     * Todos los períodos contractuales (historial completo)
     */
    public function periods(): HasMany
    {
        return $this->hasMany(ContractPeriod::class, 'contrato_id', 'contrato_id')
            ->orderBy('numero_periodo');
    }

    /**
     * Período contractual activo actual
     */
    public function currentPeriod(): BelongsTo
    {
        return $this->belongsTo(ContractPeriod::class, 'periodo_actual', 'periodo_id');
    }

    /**
     * Pagos asociados al contrato
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'contrato_id', 'contrato_id');
    }

    /**
     * Verifica si el contrato está activo
     */
    public function isActive(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    /**
     * Verifica si el contrato está cancelado
     */
    public function isCancelled(): bool
    {
        return $this->estado === 'CANCELADO';
    }

    /**
     * Verifica si el contrato está finalizado
     */
    public function isFinalized(): bool
    {
        return $this->estado === 'FINALIZADO';
    }

    /**
     * Verifica si el contrato está pendiente
     */
    public function isPending(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    /**
     * Verifica si el contrato puede ser reactivado
     * Solo los contratos CANCELADOS pueden reactivarse
     */
    public function canBeReactivated(): bool
    {
        return $this->isCancelled();
    }

    /**
     * Obtiene el número del siguiente período
     */
    public function getNextPeriodNumber(): int
    {
        return $this->periods()->max('numero_periodo') + 1;
    }
}
