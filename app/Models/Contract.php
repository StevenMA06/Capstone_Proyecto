<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $table = 'contratos';

    protected $primaryKey = 'contrato_id';

    public $timestamps = false;

    protected $fillable = [
        'codigo', 'cliente_id', 'plan_id', 'zona_id', 'responsable_id',
        'fecha_inicio', 'duracion_meses', 'fecha_fin', 'precio_contratado',
        'direccion_servicio', 'observaciones', 'estado', 'pdf_contrato_ruta',
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
}
