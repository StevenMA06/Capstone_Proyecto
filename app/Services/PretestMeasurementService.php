<?php
namespace App\Services;

use App\Support\IndicatorDefinitions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PretestMeasurementService
{
    public static function duration(string $start, string $end, int $interruptions): int
    {
        $elapsed=CarbonImmutable::parse($end,'America/Lima')->getTimestamp()-CarbonImmutable::parse($start,'America/Lima')->getTimestamp();
        if ($elapsed<=0 || $elapsed>4294967295 || $interruptions<0 || $interruptions >= $elapsed) {
            throw new \InvalidArgumentException('El fin debe ser posterior al inicio y las interrupciones deben ser menores que el tiempo transcurrido.');
        }
        return $elapsed-$interruptions;
    }

    public function record(array $data, int $userId): void
    {
        if (!isset(IndicatorDefinitions::measurable()[$data['indicador']])) {
            throw ValidationException::withMessages(['indicador'=>'Este indicador todavía no tiene inicio y fin validados.']);
        }
        if (!Schema::hasTable('observaciones_pretest')) {
            throw ValidationException::withMessages(['medicion'=>'El registro de observaciones aún no está habilitado.']);
        }
        try { $duration=self::duration($data['inicio'],$data['fin'],(int)$data['interrupciones_segundos']); }
        catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['fin'=>$e->getMessage()]); }
        // A unique form token makes double submission harmless. Measurements are immutable.
        DB::table('observaciones_pretest')->insertOrIgnore([
            'token'=>$data['token'],'indicador'=>$data['indicador'],'version_definicion'=>'levantamiento-pretest-v1',
            'inicio'=>CarbonImmutable::parse($data['inicio'],'America/Lima')->format('Y-m-d H:i:s'),
            'fin'=>CarbonImmutable::parse($data['fin'],'America/Lima')->format('Y-m-d H:i:s'),
            'interrupciones_segundos'=>(int)$data['interrupciones_segundos'],'duracion_segundos'=>$duration,
            'excepcional'=>!empty($data['excepcional']),'referencia'=>$data['referencia'],
            'observacion'=>$data['observacion'] ?? null,'registrado_por'=>$userId,'registrado_en'=>now('America/Lima'),
        ]);
    }

    public function summary(string $from,string $to,string $process): array
    {
        $enabled=Schema::hasTable('observaciones_pretest');
        $definitions=IndicatorDefinitions::forProcess($process);
        $aggregates=collect(); $recent=collect();
        if ($enabled) {
            $query=DB::table('observaciones_pretest')->where('version_definicion','levantamiento-pretest-v1')
                ->whereIn('indicador',array_keys($definitions))->whereBetween('fin',[$from.' 00:00:00',$to.' 23:59:59']);
            $aggregates=(clone $query)->select('indicador')->selectRaw('SUM(CASE WHEN excepcional = 0 THEN 1 ELSE 0 END) AS cantidad, AVG(CASE WHEN excepcional = 0 THEN duracion_segundos END) AS promedio, MIN(CASE WHEN excepcional = 0 THEN duracion_segundos END) AS minimo, MAX(CASE WHEN excepcional = 0 THEN duracion_segundos END) AS maximo, SUM(CASE WHEN excepcional = 1 THEN 1 ELSE 0 END) AS excepcionales')->groupBy('indicador')->get()->keyBy('indicador');
            $recent=(clone $query)->orderByDesc('fin')->orderByDesc('id')->limit(10)->get();
        }
        $measurements=[];
        foreach($definitions as $key=>$definition) {
            $row=$aggregates->get($key);
            $measurements[$key]=$definition+['count'=>(int)($row->cantidad ?? 0),'average'=>$row?->promedio !== null ? (float)$row->promedio/60 : null,'minimum'=>$row?->minimo !== null ? (float)$row->minimo/60 : null,'maximum'=>$row?->maximo !== null ? (float)$row->maximo/60 : null,'exceptions'=>(int)($row->excepcionales ?? 0)];
        }
        return compact('enabled','measurements','recent');
    }
}
