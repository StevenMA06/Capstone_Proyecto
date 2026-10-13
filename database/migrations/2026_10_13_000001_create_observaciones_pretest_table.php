<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('observaciones_pretest', function(Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('indicador',40);
            $table->string('version_definicion',40)->default('levantamiento-pretest-v1');
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->unsignedInteger('interrupciones_segundos')->default(0);
            $table->unsignedInteger('duracion_segundos');
            $table->boolean('excepcional')->default(false);
            $table->string('referencia',120);
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('registrado_por');
            $table->dateTime('registrado_en')->useCurrent();
            $table->index(['indicador','fin']);
        });
    }
    public function down(): void { Schema::dropIfExists('observaciones_pretest'); }
};
