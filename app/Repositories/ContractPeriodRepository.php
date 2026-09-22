<?php

namespace App\Repositories;

use App\Models\Contract;
use App\Models\ContractPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContractPeriodRepository
{
    /**
     * Crear un nuevo período contractual
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): ContractPeriod
    {
        return ContractPeriod::create($attributes);
    }

    /**
     * Actualizar un período contractual
     * @param array<string, mixed> $attributes
     */
    public function update(ContractPeriod $period, array $attributes): ContractPeriod
    {
        $period->fill($attributes)->save();

        return $period->refresh();
    }

    /**
     * Obtener todos los períodos de un contrato (historial completo)
     */
    public function findByContract(Contract $contract): Collection
    {
        return ContractPeriod::where('contrato_id', $contract->getKey())
            ->orderBy('numero_periodo')
            ->get();
    }

    /**
     * Obtener el período activo de un contrato
     */
    public function findActivePeriod(Contract $contract): ?ContractPeriod
    {
        return ContractPeriod::where('contrato_id', $contract->getKey())
            ->where('estado_periodo', 'ACTIVO')
            ->first();
    }

    /**
     * Obtener el último período de un contrato (el más reciente)
     */
    public function findLatestPeriod(Contract $contract): ?ContractPeriod
    {
        return ContractPeriod::where('contrato_id', $contract->getKey())
            ->orderByDesc('numero_periodo')
            ->first();
    }

    /**
     * Obtener el número del siguiente período para un contrato
     */
    public function getNextPeriodNumber(Contract $contract): int
    {
        $maxNumber = ContractPeriod::where('contrato_id', $contract->getKey())
            ->max('numero_periodo');

        return ($maxNumber ?? 0) + 1;
    }

    /**
     * Crear un período contractual inicial al crear/activar un contrato
     */
    public function createInitialPeriod(Contract $contract): ContractPeriod
    {
        return $this->create([
            'contrato_id' => $contract->getKey(),
            'numero_periodo' => 1,
            'fecha_inicio_periodo' => $contract->fecha_inicio,
            'fecha_fin_periodo' => $contract->fecha_fin,
            'duracion_meses_periodo' => $contract->duracion_meses,
            'estado_periodo' => 'ACTIVO',
        ]);
    }

    /**
     * Crear un nuevo período para reactivación de contrato
     * @param array<string, mixed> $data
     */
    public function createReactivationPeriod(
        Contract $contract,
        array $data
    ): ContractPeriod {
        $nextNumber = $this->getNextPeriodNumber($contract);
        
        // Calcular fecha de fin basado en fecha de inicio + duración
        $fechaInicio = CarbonImmutable::parse($data['fecha_inicio']);
        $fechaFin = $fechaInicio->addMonthsNoOverflow((int) $data['duracion_meses'])->subDay();

        return $this->create([
            'contrato_id' => $contract->getKey(),
            'numero_periodo' => $nextNumber,
            'fecha_inicio_periodo' => $data['fecha_inicio'],
            'fecha_fin_periodo' => $fechaFin->format('Y-m-d'),
            'duracion_meses_periodo' => $data['duracion_meses'],
            'estado_periodo' => 'ACTIVO',
        ]);
    }

    /**
     * Cancelar un período contractual
     * @param array<string, mixed> $data
     */
    public function cancel(
        ContractPeriod $period,
        string $motivo,
        string $origen,
        ?int $usuarioId = null
    ): ContractPeriod {
        return $this->update($period, [
            'estado_periodo' => 'CANCELADO',
            'motivo_cancelacion' => $motivo,
            'fecha_cancelacion' => now(),
            'cancelado_por' => $usuarioId,
            'origen_cancelacion' => $origen,
        ]);
    }

    /**
     * Finalizar períodos activos cuya fecha_fin_periodo ya pasó
     */
    public function finalizeExpiredPeriods(string $today): int
    {
        return DB::table('periodos_contractuales')
            ->where('estado_periodo', 'ACTIVO')
            ->where('fecha_fin_periodo', '<', $today)
            ->update(['estado_periodo' => 'FINALIZADO']);
    }

    /**
     * Obtener períodos cancelados por falta de pago
     */
    public function getCancelledByNonPayment(): Collection
    {
        return ContractPeriod::where('estado_periodo', 'CANCELADO')
            ->where('origen_cancelacion', 'AUTOMATICO_FALTA_PAGO')
            ->with('contract')
            ->get();
    }

    /**
     * Verificar si un contrato tiene períodos activos
     */
    public function hasActivePeriod(Contract $contract): bool
    {
        return ContractPeriod::where('contrato_id', $contract->getKey())
            ->where('estado_periodo', 'ACTIVO')
            ->exists();
    }

    /**
     * Contar total de períodos de un contrato
     */
    public function countPeriods(Contract $contract): int
    {
        return ContractPeriod::where('contrato_id', $contract->getKey())->count();
    }

    /**
     * Obtener estadísticas de períodos
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        $counts = DB::table('periodos_contractuales')
            ->select('estado_periodo', DB::raw('COUNT(*) as total'))
            ->groupBy('estado_periodo')
            ->pluck('total', 'estado_periodo')
            ->toArray();

        return [
            'activos' => $counts['ACTIVO'] ?? 0,
            'cancelados' => $counts['CANCELADO'] ?? 0,
            'finalizados' => $counts['FINALIZADO'] ?? 0,
            'total' => array_sum($counts),
        ];
    }
}
