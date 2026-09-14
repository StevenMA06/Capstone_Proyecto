<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::NAMES as $name) {
            Role::firstOrCreate(['nombre' => $name], ['estado' => 'ACTIVO']);
        }
    }
}
