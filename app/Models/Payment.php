<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'pagos';

    protected $primaryKey = 'pago_id';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'contrato_id', 'codigo', 'periodo_servicio', 'fecha_vencimiento',
        'fecha_pago', 'monto', 'metodo_pago', 'referencia_externa',
        'estado', 'registrado_por', 'constancia_ruta', 'observaciones',
        'recordatorio_previo_enviado_en', 'recordatorio_vencido_enviado_en',
        'notificacion_anulacion_enviada_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'datetime',
            'monto' => 'decimal:2',
            'recordatorio_previo_enviado_en' => 'datetime',
            'recordatorio_vencido_enviado_en' => 'datetime',
            'notificacion_anulacion_enviada_en' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contrato_id', 'contrato_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por', 'usuario_id');
    }

    /**
     * Scope: pagos pendientes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('estado', 'PENDIENTE');
    }

    /**
     * Scope: pagos realizados
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('estado', 'REALIZADO');
    }

    /**
     * Scope: pagos vencidos
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('estado', 'VENCIDO');
    }

    /**
     * Scope: pagos anulados
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('estado', 'ANULADO');
    }

    /**
     * Scope: pagos próximos a vencer (en los próximos N días)
     */
    public function scopeDueInDays(Builder $query, int $days = 7): Builder
    {
        $today = now()->startOfDay();
        $futureDate = now()->addDays($days)->endOfDay();

        return $query->where('estado', 'PENDIENTE')
            ->whereBetween('fecha_vencimiento', [$today, $futureDate]);
    }

    /**
     * Scope: pagos cuya fecha de vencimiento ya pasó pero aún están pendientes
     */
    public function scopeShouldBeOverdue(Builder $query): Builder
    {
        return $query->where('estado', 'PENDIENTE')
            ->where('fecha_vencimiento', '<', now()->startOfDay());
    }

    /**
     * Verifica si el pago está pendiente
     */
    public function isPending(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    /**
     * Verifica si el pago fue realizado
     */
    public function isCompleted(): bool
    {
        return $this->estado === 'REALIZADO';
    }

    /**
     * Verifica si el pago está vencido
     */
    public function isOverdue(): bool
    {
        return $this->estado === 'VENCIDO';
    }

    /**
     * Verifica si el pago está anulado
     */
    public function isCancelled(): bool
    {
        return $this->estado === 'ANULADO';
    }

    /**
     * Verifica si el pago puede ser editado
     */
    public function canBeEdited(): bool
    {
        return in_array($this->estado, ['PENDIENTE', 'VENCIDO'], true);
    }

    /**
     * Verifica si el pago puede ser anulado
     */
    public function canBeCancelled(): bool
    {
        return ! $this->isCancelled();
    }

    /**
     * Verifica si debe enviarse el recordatorio previo (7 días antes)
     */
    public function shouldSendUpcomingReminder(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        // No enviar si ya se envió
        if ($this->recordatorio_previo_enviado_en !== null) {
            return false;
        }

        // Verificar si estamos a 7 días o menos del vencimiento
        $daysUntilDue = now()->startOfDay()->diffInDays($this->fecha_vencimiento, false);

        return $daysUntilDue >= 0 && $daysUntilDue <= 7;
    }

    /**
     * Verifica si debe enviarse el recordatorio de vencido (5 días después)
     */
    public function shouldSendOverdueReminder(): bool
    {
        if (! $this->isOverdue()) {
            return false;
        }

        // No enviar si ya se envió
        if ($this->recordatorio_vencido_enviado_en !== null) {
            return false;
        }

        // Verificar si han pasado 5 días desde el vencimiento
        $daysSinceDue = $this->fecha_vencimiento->diffInDays(now()->startOfDay(), false);

        return $daysSinceDue >= 5;
    }

    /**
     * Verifica si debe anularse por falta de pago (7 días después del vencimiento)
     */
    public function shouldBeCancelledForNonPayment(): bool
    {
        if (! $this->isOverdue()) {
            return false;
        }

        // Verificar si han pasado 7 días desde el vencimiento
        $daysSinceDue = $this->fecha_vencimiento->diffInDays(now()->startOfDay(), false);

        return $daysSinceDue >= 7;
    }

    /**
     * Marca que se envió el recordatorio previo
     */
    public function markUpcomingReminderSent(): void
    {
        $this->recordatorio_previo_enviado_en = now();
        $this->save();
    }

    /**
     * Marca que se envió el recordatorio de vencido
     */
    public function markOverdueReminderSent(): void
    {
        $this->recordatorio_vencido_enviado_en = now();
        $this->save();
    }

    /**
     * Marca que se envió la notificación de anulación
     */
    public function markCancellationNotificationSent(): void
    {
        $this->notificacion_anulacion_enviada_en = now();
        $this->save();
    }
}
