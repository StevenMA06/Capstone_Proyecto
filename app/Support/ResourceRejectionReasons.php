<?php

namespace App\Support;

class ResourceRejectionReasons
{
    public const OTHER = 'OTRO';

    public static function management(?string $type = null): array
    {
        $reasons = [
            'RECURSO_NO_CORRESPONDE' => 'El recurso no corresponde a la instalación',
        ];
        if ($type === 'MATERIAL' || $type === null) {
            $reasons['CANTIDAD_NO_JUSTIFICADA'] = 'La cantidad de material no está justificada';
        }
        $reasons[self::OTHER] = 'Otro motivo';
        return $reasons;
    }

    public static function warehouse(?string $type = null, ?bool $shortage = null): array
    {
        $reasons = [];
        if (($type === 'EQUIPO' || $type === null) && $shortage !== false) $reasons['SIN_DISPONIBILIDAD'] = 'No hay unidades disponibles';
        if (($type === 'MATERIAL' || $type === null) && $shortage !== false) $reasons['STOCK_INSUFICIENTE'] = 'El stock disponible no alcanza';
        $reasons['RECURSO_NO_APTO'] = 'El recurso no está apto para instalarse';
        $reasons[self::OTHER] = 'Otro motivo';
        return $reasons;
    }

    public static function warehouseGroup(): array
    {
        return [
            'DISPONIBILIDAD_INSUFICIENTE' => 'No hay disponibilidad suficiente para completar la solicitud',
            'RECURSO_NO_APTO' => 'Un recurso solicitado no está apto para instalarse',
            self::OTHER => 'Otro motivo',
        ];
    }

    public static function label(?string $code): ?string
    {
        return (self::management() + self::warehouse() + [
            'DATOS_INCOMPLETOS' => 'Datos incompletos (motivo anterior)',
            'SOLICITUD_DUPLICADA' => 'Solicitud duplicada (motivo anterior)',
            'DISPONIBILIDAD_INSUFICIENTE' => 'No hay disponibilidad suficiente para completar la solicitud',
        ])[$code] ?? null;
    }
}
