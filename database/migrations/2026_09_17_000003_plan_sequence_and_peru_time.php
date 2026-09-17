<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('secuencias_sistema')) {
            Schema::create('secuencias_sistema', function (Blueprint $table) {
                $table->string('nombre', 40)->primary();
                $table->unsignedBigInteger('valor')->default(0);
            });
        }
        DB::table('secuencias_sistema')->insertOrIgnore(['nombre' => 'planes', 'valor' => 0]);

        // Old authentication timestamps used UTC. End pending access instead of
        // interpreting it in Lima and accidentally extending sessions by five hours.
        DB::table('sesiones_usuario')->where('estado', 'ACTIVA')->update(['estado' => 'CERRADA', 'cerrada_en' => now()]);
        DB::table('codigos_acceso_2fa')->where('estado', 'VIGENTE')->update(['estado' => 'INVALIDADO', 'invalidado_en' => now()]);
    }

    public function down(): void
    {
        // Keep the counter so an application rollback cannot reuse issued numbers.
    }
};
