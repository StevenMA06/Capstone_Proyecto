<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $groups = [
                'Administrador' => ['administrador', 'admin'],
                'Gerente' => ['gerente', 'gerencia'],
                'Usuario de oficina' => ['usuario de oficina', 'usuario de oficinas', 'personal autorizado', 'personal de oficina', 'personal de oficinas'],
                'Técnico' => ['técnico', 'tecnico', 'técnico de campo', 'tecnico de campo'],
            ];
            foreach ($groups as $name => $aliases) {
                $roles = DB::table('roles')->orderBy('rol_id')->get()->filter(fn ($r) => in_array(mb_strtolower(trim($r->nombre)), $aliases, true));
                $target = $roles->first(fn ($r) => $r->nombre === $name) ?? $roles->first();
                $id = $target?->rol_id ?? DB::table('roles')->insertGetId(['nombre' => $name, 'estado' => 'ACTIVO']);
                DB::table('roles')->where('rol_id', $id)->update(['nombre' => $name]);
                foreach ($roles as $role) {
                    if ($role->rol_id === $id) {
                        continue;
                    }
                    // Preserve disabled access when consolidating a retired role.
                    if ($role->estado !== 'ACTIVO') {
                        DB::table('usuarios')->where('rol_id', $role->rol_id)->where('estado', 'ACTIVO')->update(['estado' => 'INACTIVO']);
                    }
                    DB::table('usuarios')->where('rol_id', $role->rol_id)->update(['rol_id' => $id]);
                    DB::table('roles')->where('rol_id', $role->rol_id)->update(['estado' => 'INACTIVO']);
                }
            }
            DB::table('roles')->whereNotIn('nombre', array_keys($groups))->update(['estado' => 'INACTIVO']);
            // Permissions changed: force existing sessions to authenticate again.
            DB::table('sesiones_usuario')->where('estado', 'ACTIVA')->update(['estado' => 'CERRADA', 'cerrada_en' => now()]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La normalización conserva las cuentas. Restaura una copia de seguridad para recuperar los roles anteriores.');
    }
};
