<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'app:create-user {--username= : Nombre de usuario} {--role=Usuario de oficina : Nombre del rol activo}';

    protected $description = 'Crea una cuenta en usuarios sin contraseñas predeterminadas';

    public function handle(UserService $users): int
    {
        $role = Role::whereIn('nombre', Role::NAMES)->where('nombre', $this->option('role'))->where('estado', 'ACTIVO')->first();
        if (! $role) {
            $this->error('Rol activo no encontrado. Ejecuta php artisan db:seed o usa el nombre de un rol existente.');

            return self::FAILURE;
        }
        $nombre_usuario = Str::lower(trim((string) ($this->option('username') ?: $this->ask('Nombre de usuario'))));
        $correo = Str::lower(trim((string) $this->ask('Correo electrónico')));
        $password = $this->secret('Contraseña (mínimo 12 caracteres, letras y números)');
        $password_confirmation = $this->secret('Confirma la contraseña');
        $validator = Validator::make(compact('nombre_usuario', 'correo', 'password', 'password_confirmation'), [
            'nombre_usuario' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('usuarios')],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios')],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $users->create($validator->validated() + ['rol_id' => $role->getKey(), 'empleado_id' => null]);
        $this->info('Usuario creado. Ya puedes iniciar sesión.');

        return self::SUCCESS;
    }
}
