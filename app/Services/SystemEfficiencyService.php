<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemEfficiencyService
{
    public function summary(string $from, string $to, string $process): array
    {
        $automatic = app(AutomaticIndicatorService::class);
        $all = [
            'clientes' => $automatic->registration('clientes', 'Registro de clientes', $from, $to,
                'Primera interacción válida con Tipo de documento o Número de documento.', 'Guardado exitoso al pulsar «Registrar cliente».',
                'Registro creado en clientes.', 'No inicia al abrir el formulario. Conserva el inicio tras errores corregibles y descuenta inactividad.'),
            'contratos' => $automatic->registration('contratos', 'Registro de contratos', $from, $to,
                'Primera selección válida del Cliente.', 'Guardado exitoso al pulsar «Guardar contrato».',
                'Contrato creado y pedido asociado disponible.', 'No inicia al abrir el formulario. Conserva el inicio tras errores corregibles y descuenta inactividad.'),
            'pagos' => $automatic->registration('pagos', 'Registro de pagos', $from, $to,
                'Primera interacción válida con Fecha de pago o Método de pago.', 'Guardado exitoso al confirmar el pago.',
                'Pago creado en pagos.', 'No inicia al abrir el formulario. Conserva el inicio tras errores corregibles y descuenta inactividad.'),
            'instalaciones' => $this->installations($from, $to, 'Ejecución de instalaciones'),
            'pedido_programacion' => $automatic->orderProgramming($from, $to),
            'equipos' => $automatic->registration('equipos', 'Registro de equipos', $from, $to,
                'Primera introducción de datos en N° Serie.', 'Guardado exitoso al pulsar «Registrar equipo».',
                'Equipo creado y movimiento de inventario asociado.', 'No inicia al abrir el formulario. Conserva el inicio tras errores corregibles y descuenta inactividad.'),
            'materiales' => $automatic->registration('materiales', 'Registro de materiales', $from, $to,
                'Primera introducción de datos en Nombre del material.', 'Guardado exitoso al pulsar «Registrar material».',
                'Material creado y movimiento de inventario asociado si corresponde.', 'No inicia al abrir el formulario. Conserva el inicio tras errores corregibles y descuenta inactividad.'),
        ];
        $keys = match ($process) {
            'clientes' => ['clientes'], 'contratos' => ['contratos'], 'pagos' => ['pagos'],
            'instalaciones' => ['instalaciones', 'pedido_programacion'], 'inventario' => ['equipos', 'materiales'],
            default => array_keys($all),
        };
        $measurements = array_intersect_key($all, array_flip($keys));

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
            'count_label' => 'instalaciones finalizadas',
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

