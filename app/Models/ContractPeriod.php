<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractPeriod extends Model
{
    protected $table = 'periodos_contractuales';

    protected $primaryKey = 'periodo_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'contrato_id',
        'numero_periodo',
        'fecha_inicio_periodo',
        'fecha_fin_periodo',
        'duracion_meses_periodo',
        'estado_periodo',
        'motivo_cancelacion',
        'fecha_cancelacion',
        'cancelado_por',
        'origen_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio_periodo' => 'date',
            'fecha_fin_periodo' => 'date',
            'fecha_cancelacion' => 'datetime',
        ];
    }

    /**
     * Contrato al que pertenece este período
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contrato_id', 'contrato_id');
    }

    /**
     * Usuario que canceló el período (si aplica)
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por', 'usuario_id');
    }

    /**
     * Verifica si el período está activo
     */
    public function isActive(): bool
    {
        return $this->estado_periodo === 'ACTIVO';
    }

    /**
     * Verifica si el período está cancelado
     */
    public function isCancelled(): bool
    {
        return $this->estado_periodo === 'CANCELADO';
    }

    /**
     * Verifica si el período está finalizado
     */
    public function isFinalized(): bool
    {
        return $this->estado_periodo === 'FINALIZADO';
    }

    /**
     * Verifica si la cancelación fue automática por falta de pago
     */
    public function wasCancelledByNonPayment(): bool
    {
        return $this->isCancelled() && $this->origen_cancelacion === 'AUTOMATICO_FALTA_PAGO';
    }

    /**
     * Verifica si la cancelación fue manual
     */
    public function wasCancelledManually(): bool
    {
        return $this->isCancelled() && $this->origen_cancelacion === 'MANUAL';
    }

    /**
     * Calcula la duración en días del período
     */
    public function getDurationInDays(): int
    {
        return $this->fecha_inicio_periodo->diffInDays($this->fecha_fin_periodo) + 1;
    }

    /**
     * Verifica si el período ya finalizó por fecha
     */
    public function hasExpired(): bool
    {
        return $this->fecha_fin_periodo < now()->startOfDay();
    }

    /**
     * Verifica si el período está vigente (activo y no expirado)
     */
    public function isCurrent(): bool
    {
        return $this->isActive() && !$this->hasExpired();
    }
}
