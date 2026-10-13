<?php

namespace App\Support;

final class InstallationOrderCancellationReasons
{
    public static function all(): array
    {
        return [
            'CLIENTE_DESISTE' => 'El cliente desistió de la instalación',
            'CLIENTE_NO_DISPONIBLE' => 'El cliente no puede atender la visita acordada',
            'NO_REQUIERE_SERVICIO' => 'El cliente ya no requiere el servicio',
            'VIABILIDAD_TECNICA' => 'No fue posible completar la instalación por una condición técnica',
            'OTRO' => 'Otro motivo',
        ];
    }
}
