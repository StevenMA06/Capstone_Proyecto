<?php

use App\Models\Role;
use App\Support\AccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $before = DB::table('rol_permiso as rp')->join('permisos as p', 'p.permiso_id', '=', 'rp.permiso_id')
                ->where('rp.permitido', true)->get(['rp.rol_id', 'p.codigo']);
            foreach (['Gerente'=>Role::MANAGER, 'Usuario de oficina'=>Role::OFFICE, 'Técnico'=>Role::TECHNICIAN] as $old=>$name) {
                $oldRole = DB::table('roles')->where('nombre', $old)->first();
                if (!$oldRole) continue;
                $target = DB::table('roles')->where('nombre', $name)->first();
                if ($target && $target->rol_id !== $oldRole->rol_id) {
                    // Keep both IDs and assignments; preserve an existing custom role without elevating it.
                    DB::table('roles')->where('rol_id', $target->rol_id)->update(['nombre' => mb_substr($name,0,30).' (personalizado '.$target->rol_id.')']);
                }
                DB::table('roles')->where('rol_id', $oldRole->rol_id)->update(['nombre'=>$name]);
            }
            foreach (AccessCatalog::permissions() as $code=>$data) {
                DB::table('permisos')->updateOrInsert(['codigo'=>$code], $data+['estado'=>'ACTIVO']);
            }
            foreach ([Role::WAREHOUSE,Role::IT,Role::INSTALLATIONS] as $name) {
                if (!DB::table('roles')->where('nombre',$name)->exists()) DB::table('roles')->insert(['nombre'=>$name,'descripcion'=>'Rol de la matriz de acceso','estado'=>'ACTIVO']);
            }
            $catalog = AccessCatalog::permissions();
            foreach (Role::all() as $role) {
                $codes = AccessCatalog::defaults($role->nombre);
                if (!in_array($role->nombre, Role::NAMES, true)) {
                    foreach ($before->where('rol_id',$role->getKey()) as $permission) {
                        $code=$permission->codigo;
                        if (str_ends_with($code,'.manage') && !in_array($code,['roles.manage','user-roles.manage'])) {
                            $prefix=explode('.',$code)[0].'.';
                            foreach (array_keys($catalog) as $candidate) if (str_starts_with($candidate,$prefix) && !AccessCatalog::reserved($candidate) && $candidate !== 'technician.supervise') $codes[]=$candidate;
                        } elseif ($code==='installations.view') $codes[]='orders.view';
                        elseif (isset($catalog[$code]) && !AccessCatalog::reserved($code)) $codes[]=$code;
                    }
                    foreach ($codes as $code) $codes=array_merge($codes,AccessCatalog::dependencies($code));
                }
                DB::table('rol_permiso')->where('rol_id',$role->getKey())->delete();
                foreach (DB::table('permisos')->whereIn('codigo',array_unique($codes))->pluck('permiso_id') as $id) {
                    DB::table('rol_permiso')->insert(['rol_id'=>$role->getKey(),'permiso_id'=>$id,'permitido'=>true]);
                }
            }
            DB::table('permisos')->whereNotIn('codigo',array_keys($catalog))->update(['estado'=>'INACTIVO']);
            DB::table('sesiones_usuario')->where('estado','ACTIVA')->update(['estado'=>'CERRADA','cerrada_en'=>now()]);
            DB::table('codigos_acceso_2fa')->where('estado','VIGENTE')->update(['estado'=>'INVALIDADO','invalidado_en'=>now()]);
            DB::table('auditoria_permisos')->insert(['actor_id'=>null,'accion'=>'APLICAR_MATRIZ','entidad'=>'sistema','entidad_id'=>0,'antes'=>json_encode($before),'despues'=>json_encode(['roles'=>Role::NAMES]),'fecha_hora'=>now()]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Restaura una copia de seguridad para recuperar la matriz y sus permisos anteriores.');
    }
};
