<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AutomaticIndicatorService
{
    public const INACTIVITY_SECONDS = 120;

    public function complete(Request $request, User $user, string $indicator, string $referenceType, int $referenceId, ?int $orderId = null, ?int $attempt = null): void
    {
        if (! Schema::hasTable('mediciones_automaticas')) return;

        $token = (string) $request->input('indicador_token');
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $token)) return;

        try {
            // El navegador envía ISO 8601 en UTC (sufijo Z). parse() conserva ese
            // desplazamiento aunque reciba una zona por defecto. Normalizamos ambos
            // instantes a Lima antes de guardarlos junto con fin_en.
            $start = CarbonImmutable::parse((string) $request->input('indicador_inicio'))->setTimezone('America/Lima');
            $last = CarbonImmutable::parse((string) $request->input('indicador_ultima_interaccion', $request->input('indicador_inicio')))->setTimezone('America/Lima');
        } catch (\Throwable) {
            return;
        }

        $end = now('America/Lima')->toImmutable();
        if ($start->greaterThan($end) || $last->lessThan($start)) return;
        if ($last->greaterThan($end)) $last = $end;

        $elapsed = max(0, $end->getTimestamp() - $start->getTimestamp());
        $active = max(0, min($elapsed, (int) floor((float) $request->input('indicador_activo_segundos', 0))));
        // La pausa se registra explícitamente en cada intervalo que supera 120 s.
        // No se infiere desde elapsed - active, pues esa diferencia puede ser solo
        // precisión subsegundo que DATETIME no conserva.
        $discarded = max(0, min(max(0, $elapsed - $active), (int) floor((float) $request->input('indicador_pausa_descartada_segundos', 0))));

        DB::table('mediciones_automaticas')->insertOrIgnore([
            'token' => $token, 'indicador' => $indicator, 'pedido_id' => $orderId, 'intento' => $attempt,
            'referencia_tipo' => $referenceType, 'referencia_id' => $referenceId, 'usuario_id' => $user->getKey(),
            'inicio_en' => $start->format('Y-m-d H:i:s'), 'ultima_interaccion_en' => $last->format('Y-m-d H:i:s'),
            'fin_en' => $end->format('Y-m-d H:i:s'), 'duracion_activa_segundos' => $active,
            'pausa_descartada_segundos' => $discarded, 'estado' => 'COMPLETADA',
            'registrado_en' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    public function registration(string $indicator, string $title, string $from, string $to, string $start, string $end, string $evidence, string $scope): array
    {
        if (! Schema::hasTable('mediciones_automaticas')) return $this->unavailable($title, 'La base de datos aún no cuenta con el registro automático de este indicador.');
        $row = DB::table('mediciones_automaticas')->where('indicador', $indicator)->where('estado', 'COMPLETADA')
            ->whereBetween('fin_en', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('COUNT(*) AS cantidad, AVG(duracion_activa_segundos) AS promedio, MIN(duracion_activa_segundos) AS minimo, MAX(duracion_activa_segundos) AS maximo')->first();
        return $this->measurement($title, $row, $start, $end, $evidence, $scope,
            'Promedio de la duración activa de los registros completados dentro del período, con pausas de más de 2 minutos descontadas.');
    }

    public function orderProgramming(string $from, string $to): array
    {
        $title = 'Tiempo promedio de registro y programación de pedidos de instalación';
        if (! Schema::hasTable('mediciones_automaticas')) return $this->unavailable($title, 'La base de datos aún no cuenta con las mediciones del pedido.');
        $orders = DB::table('mediciones_automaticas as m')->join('pedidos_instalacion as p', 'p.pedido_id', '=', 'm.pedido_id')
            ->whereIn('m.indicador', ['pedido_recursos', 'pedido_asignacion'])->where('m.estado', 'COMPLETADA')
            ->whereNotNull('p.asignado_en')->whereBetween('p.asignado_en', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupBy('m.pedido_id')->havingRaw("SUM(CASE WHEN m.indicador = 'pedido_recursos' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN m.indicador = 'pedido_asignacion' THEN 1 ELSE 0 END) > 0")
            ->select('m.pedido_id')->selectRaw('SUM(m.duracion_activa_segundos) AS duracion')->get();
        $count = $orders->count();
        $durations = $orders->pluck('duracion');
        return [
            'title' => $title, 'available' => true, 'count' => $count, 'count_label' => 'pedidos programados',
            'average' => $count ? (float) $durations->avg() / 60 : null,
            'minimum' => $count ? (float) $durations->min() / 60 : null,
            'maximum' => $count ? (float) $durations->max() / 60 : null,
            'start' => 'Apertura de «Solicitar recursos a Almacén»; el pedido ya está creado por el contrato asociado.',
            'end' => 'Confirmación persistida de «Confirmar asignación y avisar», cuando el pedido queda Programado.',
            'evidence' => 'Tramos de recursos y asignación en mediciones_automaticas, vinculados al pedido.',
            'scope' => 'Incluye intentos de recursos rechazados y la asignación. Excluye Gerencia, Almacén y periodos de inactividad mayores de 2 minutos.',
            'calculation' => 'Por pedido: suma de tramos activos de recursos y asignación. El período se determina por asignado_en.',
        ];
    }

    private function measurement(string $title, object $row, string $start, string $end, string $evidence, string $scope, string $calculation): array
    {
        return ['title' => $title, 'available' => true, 'count' => (int) ($row->cantidad ?? 0), 'count_label' => 'registros completados',
            'average' => $row?->promedio !== null ? (float) $row->promedio / 60 : null,
            'minimum' => $row?->minimo !== null ? (float) $row->minimo / 60 : null,
            'maximum' => $row?->maximo !== null ? (float) $row->maximo / 60 : null,
            'start' => $start, 'end' => $end, 'evidence' => $evidence, 'scope' => $scope, 'calculation' => $calculation];
    }

    private function unavailable(string $title, string $reason): array
    {
        return ['title' => $title, 'available' => false, 'reason' => $reason];
    }
}
