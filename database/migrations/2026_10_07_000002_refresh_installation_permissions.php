<?php

use App\Support\AccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $catalog = AccessCatalog::permissions();
        foreach (['orders.schedule', 'technician.start', 'technician.observation'] as $code) {
            DB::table('permisos')->where('codigo', $code)->update($catalog[$code]);
        }
    }

    public function down(): void {}
};
