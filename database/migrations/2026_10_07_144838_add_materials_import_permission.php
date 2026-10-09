<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Crear el permiso materials.import
        $permisoId = DB::table('permisos')->insertGetId([
            'codigo' => 'materials.import',
            'modulo' => 'Materiales',
            'accion' => 'Importar materiales',
            'descripcion' => 'Descargar la plantilla, previsualizar, confirmar o cancelar una importación de materiales.',
            'estado' => 'ACTIVO',
        ]);

        // Asignar el permiso a roles que gestionan materiales
        $roles = DB::table('roles')
            ->whereIn('nombre', ['Administrador', 'Gerente', 'Gerencia'])
            ->where('estado', 'ACTIVO')
            ->pluck('rol_id');

        foreach ($roles as $rolId) {
            DB::table('rol_permiso')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
                'permitido' => true,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('rol_permiso')
            ->whereIn('permiso_id', function($query) {
                $query->select('permiso_id')
                    ->from('permisos')
                    ->where('codigo', 'materials.import');
            })
            ->delete();

        DB::table('permisos')->where('codigo', 'materials.import')->delete();
    }
};
