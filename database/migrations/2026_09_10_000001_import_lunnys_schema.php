<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Esta base requiere MySQL o MariaDB. Usa la base de XAMPP, también para las pruebas.');
        }
        $sql = file_get_contents(database_path('sql/Db_LunnysPeru.sql'));
        $sql = preg_replace('/^--.*$/m', '', $sql);
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '' || preg_match('/^(CREATE DATABASE|USE)\b/i', $statement)) {
                continue;
            }
            if (! preg_match('/^CREATE TABLE\s+/i', $statement)) {
                throw new RuntimeException('El archivo debe contener únicamente definiciones CREATE TABLE.');
            }
            DB::unprepared(preg_replace('/^CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $statement));
        }
    }

    public function down(): void
    {
        throw new RuntimeException('No se elimina automáticamente la base compartida del equipo. Restaura una copia de seguridad si necesitas revertirla.');
    }
};
