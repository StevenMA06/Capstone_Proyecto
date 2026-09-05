<?php

namespace App\Console\Commands;

use App\Repositories\UserRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Crea un usuario con una contraseña privada para iniciar sesión';

    public function handle(UserRepository $users): int
    {
        $name = $this->ask('Nombre');
        $email = Str::lower(trim((string) $this->ask('Correo electrónico')));
        $password = $this->secret('Contraseña (mínimo 12 caracteres, letras y números)');
        $password_confirmation = $this->secret('Confirma la contraseña');
        $validator = Validator::make(compact('name', 'email', 'password', 'password_confirmation'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $users->create($name, $email, $password);
        $this->info('Usuario creado. Ya puedes iniciar sesión.');

        return self::SUCCESS;
    }
}
