<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemEfficiencyService
{
    private const PROCESSES = [
        'clientes' => ['Registro de clientes', 'El sistema aún no registra un evento de inicio y otro de finalización para este flujo.'],
        'contratos' => ['Registro de contratos', 'El sistema aún no registra un evento de inicio y otro de finalización para este flujo.'],
        'pagos' => ['Registro de pagos', 'El sistema aún no registra un evento de inicio y otro de finalización para este flujo.'],
        'instalaciones' => ['Ejecución de instalaciones', null],
        'inventario' => ['Registro de inventario', 'El sistema aún no registra un evento de inicio y otro de finalización para este flujo.'],
    ];

    public function summary(string $from, string $to, string $process): array
    {
        $definitions = $process === 'all'
            ? self::PROCESSES
            : array_intersect_key(self::PROCESSES, [$process => true]);

        $measurements = [];
        foreach ($definitions as $key => [$title, $unavailableReason]) {
            $measurements[$key] = $key === 'instalaciones'
                ? $this->installations($from, $to, $title)
                : $this->unavailable($title, $unavailableReason);
        }

        return compact('measurements');
    }

    private function installations(string $from, string $to, string $title): array
    {
        if (!Schema::hasTable('ejecuciones_instalacion') || !Schema::hasColumns('ejecuciones_instalacion', ['inicio_en', 'fin_en'])) {
            return $this->unavailable($title, 'La base de datos aún no cuenta con las marcas de inicio y fin de la instalación.');
        }

        $row = DB::table('ejecuciones_instalacion')
            ->where('estado', 'COMPLETADA')
            ->whereNotNull('inicio_en')
            ->whereNotNull('fin_en')
            ->whereBetween('fin_en', [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereRaw('fin_en > inicio_en')
            ->selectRaw('COUNT(*) AS cantidad, AVG(TIMESTAMPDIFF(SECOND, inicio_en, fin_en)) AS promedio, MIN(TIMESTAMPDIFF(SECOND, inicio_en, fin_en)) AS minimo, MAX(TIMESTAMPDIFF(SECOND, inicio_en, fin_en)) AS maximo')
            ->first();

        return [
            'title' => $title,
            'available' => true,
            'count' => (int) ($row->cantidad ?? 0),
            'average' => $row?->promedio !== null ? (float) $row->promedio / 60 : null,
            'minimum' => $row?->minimo !== null ? (float) $row->minimo / 60 : null,
            'maximum' => $row?->maximo !== null ? (float) $row->maximo / 60 : null,
            'start' => 'El técnico pulsa «Iniciar instalación» en su panel.',
            'end' => 'El sistema registra la finalización cuando confirma en Drive la fotografía y el contrato firmado verificado.',
            'evidence' => 'Las marcas inicio_en y fin_en de ejecuciones_instalacion, vinculadas al pedido.',
            'scope' => 'Mide el tiempo transcurrido entre ambas acciones registradas por el sistema. No descuenta pausas porque el sistema todavía no registra su inicio y fin.',
            'calculation' => 'Promedio de (fin_en − inicio_en) para las instalaciones finalizadas dentro del período seleccionado. Conversión de segundos a minutos.',
        ];
    }

    private function unavailable(string $title, string $reason): array
    {
        return ['title' => $title, 'available' => false, 'reason' => $reason];
    }
}

