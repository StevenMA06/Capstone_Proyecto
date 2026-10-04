<?php

use App\Models\Role;
use App\Support\AccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id'); $table->unsignedBigInteger('rol_id');
            $table->primary(['usuario_id', 'rol_id']);
            $table->foreign('usuario_id')->references('usuario_id')->on('usuarios')->restrictOnDelete();
            $table->foreign('rol_id')->references('rol_id')->on('roles')->restrictOnDelete();
        });
        Schema::create('auditoria_permisos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('accion', 60); $table->string('entidad', 30); $table->unsignedBigInteger('entidad_id');
            $table->json('antes')->nullable(); $table->json('despues')->nullable(); $table->dateTime('fecha_hora');
        });
        DB::transaction(function () {
            foreach (AccessCatalog::permissions() as $code => $data) {
                DB::table('permisos')->updateOrInsert(['codigo' => $code], $data + ['estado' => 'ACTIVO']);
            }
            foreach (Role::all() as $role) AccessCatalog::seedRole($role);
            DB::table('usuarios')->orderBy('usuario_id')->chunkById(200, function ($users) {
                foreach ($users as $user) DB::table('usuario_rol')->insert(['usuario_id' => $user->usuario_id, 'rol_id' => $user->rol_id]);
            }, 'usuario_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión perdería asignaciones y auditoría. Restaura una copia previa o prepara una migración de reversión.');
    }
};
