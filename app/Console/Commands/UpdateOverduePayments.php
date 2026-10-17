<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateOverduePayments extends Command
{
    protected $signature = 'payments:update-overdue {--dry-run : Mostrar cambios sin guardarlos}';
    protected $description = 'Marca VENCIDO al cumplir cuatro días e IMPAGO al superar cuatro días de atraso';

    public function handle(): int
    {
        $cutoff = now('America/Lima')->startOfDay()->subDays(4)->toDateString();
        $query = DB::table('pagos')->where('estado', 'PENDIENTE')->whereDate('fecha_vencimiento', '<=', $cutoff);
        if ($this->option('dry-run')) {
            $this->info('Simulación: '.$query->count().' pagos pasarían a VENCIDO. No se guardaron cambios.');
            $impago = DB::table('pagos')->whereIn('estado', ['PENDIENTE', 'VENCIDO'])->whereDate('fecha_vencimiento', '<', $cutoff)->count();
            $this->info('Simulación: '.$impago.' pagos pasarían a IMPAGO.');
        } else {
            // Una sola actualización mantiene los pagos realizados/anulados y es idempotente.
            [$overdue, $unpaid] = DB::transaction(function () use ($query, $cutoff) {
                $overdue = $query->update(['estado' => 'VENCIDO']);
                // Conserva IMPAGO a partir del quinto día y el flujo existente de impago de contratos.
                $unpaid = DB::update('UPDATE pagos SET estado = ?, observaciones = CONCAT(COALESCE(observaciones, ?), ?) WHERE estado = ? AND DATE(fecha_vencimiento) < ?', [
                    'IMPAGO', '', "\n[".now('America/Lima')->format('Y-m-d H:i:s').'] Marcado como IMPAGO al superar cuatro días de atraso.', 'VENCIDO', $cutoff,
                ]);
                return [$overdue, $unpaid];
            });
            $this->info($overdue.' pagos actualizados a VENCIDO; '.$unpaid.' pagos actualizados a IMPAGO.');
        }
        return self::SUCCESS;
    }
}
