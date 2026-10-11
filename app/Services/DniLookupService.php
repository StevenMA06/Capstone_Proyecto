<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DniLookupService
{
    public function lookup(string $dni): array
    {
        $key = trim((string) config('services.apiperu.key'));
        if ($key === '') {
            throw new RuntimeException('La consulta de DNI no está configurada. Puedes completar los datos manualmente.', 503);
        }

        try {
            $response = Http::withToken($key)->acceptJson()->asJson()
                ->connectTimeout(5)->timeout(10)
                ->post('https://api.apiperu.pe/dni', ['dni' => $dni]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No se pudo conectar con la consulta de DNI. Puedes completar los datos manualmente.', 503);
        }

        if ($response->status() === 429) {
            throw new RuntimeException('Se alcanzó el límite de consultas de API Perú. Puedes completar los datos manualmente.', 429);
        }
        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException('API Perú rechazó el acceso. Solicita al administrador revisar la clave y el plan. Puedes completar los datos manualmente.', 503);
        }
        if ($response->status() === 404) {
            throw new RuntimeException('No se encontraron datos para este DNI. Puedes completar los datos manualmente.', 404);
        }
        if (! $response->successful()) {
            throw new RuntimeException('La consulta de DNI no está disponible en este momento. Puedes completar los datos manualmente.', 503);
        }

        $data = $response->json('data');
        if ($response->json('success') !== true || ! is_array($data)
            || ($data['numero'] ?? null) !== $dni
            || ! is_string($data['nombres'] ?? null) || trim($data['nombres']) === ''
            || ! is_string($data['apellido_paterno'] ?? null) || trim($data['apellido_paterno']) === ''
            || (isset($data['apellido_materno']) && ! is_string($data['apellido_materno']))) {
            throw new RuntimeException('La API no devolvió datos válidos para este DNI. Puedes completar los datos manualmente.', 503);
        }

        $address = $data['direccion_completa'] ?? '';
        if (! is_string($address) || trim($address) === '') $address = $data['direccion'] ?? '';

        return [
            'dni' => $dni,
            'nombres' => mb_substr(trim($data['nombres']), 0, 100),
            'apellidos' => mb_substr(trim($data['apellido_paterno'].' '.($data['apellido_materno'] ?? '')), 0, 120),
            'direccion' => is_string($address) ? mb_substr(trim($address), 0, 255) : '',
        ];
    }
}
