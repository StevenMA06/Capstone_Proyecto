<?php

namespace App\Services;

use App\Repositories\SaleRepository;
use Carbon\CarbonImmutable;

class SaleService
{
    public const STATES = ['CONFIRMADA', 'ANULADA'];

    public function __construct(private SaleRepository $sales) {}

    public function dashboard(array $filters): array
    {
        if (isset($filters['estados'])) {
            $filters['estados'] = array_values(array_unique($filters['estados']));
        }

        $daily = isset($filters['desde'], $filters['hasta'])
            && CarbonImmutable::parse($filters['desde'])->diffInDays(CarbonImmutable::parse($filters['hasta'])) <= 31;
        $employeeData = $this->sales->byEmployee($filters);

        return [
            'filters' => $filters,
            'states' => self::STATES,
            'employees' => $this->sales->employees(),
            'summary' => $this->sales->summary($filters),
            'byEmployee' => $employeeData,
            'employeeMax' => max(1, (int) $employeeData->max('quantity')),
            'byState' => $this->sales->byState($filters),
            'byPeriod' => $this->sales->byPeriod($filters, $daily),
            'daily' => $daily,
            'sales' => $this->sales->results($filters),
        ];
    }
}
