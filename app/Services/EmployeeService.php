<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function save(?Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $record = $employee ? Employee::lockForUpdate()->findOrFail($employee->getKey()) : new Employee;
            $record->fill($data);
            if (! $record->exists) {
                $record->estado = 'ACTIVO';
            }
            if ($record->hasEnded()) {
                $record->estado = 'INACTIVO';
            }
            $record->save();
            if ($record->estado === 'INACTIVO') {
                $this->disableAccounts($record);
            }

            return $record;
        }, 3);
    }

    public function setActive(Employee $employee, bool $active): void
    {
        DB::transaction(function () use ($employee, $active) {
            $record = Employee::lockForUpdate()->findOrFail($employee->getKey());
            if ($active && $record->hasEnded()) {
                throw ValidationException::withMessages(['fecha_cese' => 'Actualiza la fecha de cese antes de habilitar al empleado.']);
            }
            $record->estado = $active ? 'ACTIVO' : 'INACTIVO';
            $record->save();
            if (! $active) {
                $this->disableAccounts($record);
            }
        }, 3);
    }

    public function expire(): int
    {
        $count = 0;
        Employee::where('fecha_cese', '<', Employee::today())
            ->where(fn ($q) => $q->where('estado', 'ACTIVO')->orWhereHas('accounts', fn ($a) => $a->where('estado', '<>', 'INACTIVO')))
            ->select('empleado_id')->chunkById(100, function ($employees) use (&$count) {
                foreach ($employees as $employee) {
                    $count += DB::transaction(function () use ($employee) {
                        $record = Employee::lockForUpdate()->find($employee->getKey());
                        if (! $record || ! $record->hasEnded()) {
                            return 0;
                        }
                        $record->update(['estado' => 'INACTIVO']);
                        $this->disableAccounts($record);

                        return 1;
                    }, 3);
                }
            }, 'empleado_id');

        return $count;
    }

    private function disableAccounts(Employee $employee): void
    {
        foreach (User::where('empleado_id', $employee->getKey())->orderBy('usuario_id')->lockForUpdate()->get() as $user) {
            $user->update(['estado' => 'INACTIVO']);
            app(UserRepository::class)->revokeSessions($user);
            DB::table('codigos_acceso_2fa')->where('usuario_id', $user->getKey())->where('estado', 'VIGENTE')
                ->update(['estado' => 'INVALIDADO', 'invalidado_en' => now()]);
        }
    }
}
