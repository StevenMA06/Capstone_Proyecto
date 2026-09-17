<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table): void {
            $table->string('firma_revision', 20)->nullable();
            $table->dateTime('firma_validada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table): void {
            $table->dropColumn(['firma_revision', 'firma_validada_en']);
        });
    }
};
