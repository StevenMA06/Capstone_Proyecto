<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permisos')->where('codigo', 'orders.create')->update(['estado' => 'INACTIVO']);
    }

    public function down(): void
    {
        DB::table('permisos')->where('codigo', 'orders.create')->update(['estado' => 'ACTIVO']);
    }
};
