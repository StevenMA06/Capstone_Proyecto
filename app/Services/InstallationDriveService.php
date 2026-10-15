<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InstallationDriveService
{
    public function createFolder(): string
    {
        if (! filled(config('services.installation_drive.client_id'))
            || ! filled(config('services.installation_drive.client_secret'))
            || ! filled(config('services.installation_drive.refresh_token'))) {
            throw new \RuntimeException('Configura primero el cliente OAuth y el token de actualización en .env.');
        }
        $response = Http::withToken($this->token())->post('https://www.googleapis.com/drive/v3/files?fields=id', [
            'name' => 'Lunnys Perú - Evidencias de instalaciones',
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        $response->throw();
        $id = $response->json('id');
        if (! is_string($id) || $id === '') throw new \RuntimeException('Google Drive no devolvió el ID de la carpeta.');
        return $id;
    }

    public function configured(): bool
    {
        $oauth = filled(config('services.installation_drive.client_id'))
            && filled(config('services.installation_drive.client_secret'))
            && filled(config('services.installation_drive.refresh_token'));
        $serviceAccount = filled(config('services.installation_drive.credentials_path'))
            && is_file(config('services.installation_drive.credentials_path'));
        return filled(config('services.installation_drive.folder_id')) && ($oauth || $serviceAccount);
    }

    public function authorizationExpired(int $orderId): bool
    {
        return DB::table('evidencias_drive_instalacion')->where('pedido_id', $orderId)
            ->where('estado', 'PENDIENTE')
            ->where(fn ($query) => $query->where('error', 'like', '%invalid_grant%')
                ->orWhere('error', 'AUTH_EXPIRED'))
            ->exists();
    }

    public function sync(int $orderId, string $type, int $evidenceId, string $path, ?string $name, ?string $mime): bool
    {
        $key = ['tipo' => $type, 'evidencia_id' => $evidenceId];
        $existing = DB::table('evidencias_drive_instalacion')->where($key)->first();
        $source = Storage::disk('local')->path($path);
        $hash = is_file($source) ? hash_file('sha256', $source) : null;
        if ($existing?->estado === 'CONFIRMADO' && $existing->drive_file_id
            && $hash && $existing->source_sha256 === $hash) {
            app(InstallationCompletionService::class)->tryCompleteAfterDriveSync($orderId);
            return true;
        }
        if (! $existing) {
            DB::table('evidencias_drive_instalacion')->insert($key + [
                'pedido_id' => $orderId, 'estado' => 'PENDIENTE',
                'created_at' => now('America/Lima'), 'updated_at' => now('America/Lima'),
            ]);
        }
        if (! $this->configured()) return false;

        try {
            $token = $this->token();
            $folderId = $this->evidenceFolder($token, $orderId, $type);
            $fileId = $existing?->source_sha256 !== $hash ? null : $existing?->drive_file_id;
            if (! $fileId) {
                $bytes = Storage::disk('local')->get($path);
                if (! is_string($bytes)) throw new \RuntimeException('No se pudo leer el archivo local.');
                $boundary = 'lunnys_'.bin2hex(random_bytes(12));
                $metadata = json_encode([
                    'name' => $name ?: basename($path), 'parents' => [$folderId],
                ], JSON_THROW_ON_ERROR);
                $body = '--'.$boundary."\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n".$metadata
                    ."\r\n--".$boundary."\r\nContent-Type: ".($mime ?: 'application/octet-stream')."\r\n\r\n"
                    .$bytes."\r\n--".$boundary."--\r\n";
                $response = Http::withToken($token)->withBody($body, 'multipart/related; boundary='.$boundary)
                    ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,parents,trashed');
                $response->throw();
                $fileId = $response->json('id');
                if (! is_string($fileId) || $fileId === '') throw new \RuntimeException('Drive no devolvió el identificador del archivo.');
                DB::table('evidencias_drive_instalacion')->where($key)->update(['drive_file_id' => $fileId, 'source_sha256' => $hash]);
            }
            $check = $this->file($token, $fileId);
            if ($check->status() === 404 && $existing?->drive_file_id) {
                DB::table('evidencias_drive_instalacion')->where($key)->update(['drive_file_id' => null]);
                throw new \RuntimeException('El archivo anterior ya no existe en Drive. Vuelve a reintentar el envío.');
            }
            $check->throw();
            if (! $check->json('trashed') && in_array(config('services.installation_drive.folder_id'), $check->json('parents') ?? [], true)) {
                $this->moveFromRoot($token, $fileId, $folderId);
                $check = $this->file($token, $fileId);
            }
            if ($check->json('trashed') || ! in_array($folderId, $check->json('parents') ?? [], true)) {
                throw new \RuntimeException('El archivo no quedó confirmado en la carpeta configurada.');
            }
            DB::table('evidencias_drive_instalacion')->where($key)->update([
                'drive_file_id' => $fileId, 'estado' => 'CONFIRMADO', 'error' => null,
                'source_sha256' => $hash,
                'confirmado_en' => now('America/Lima'), 'updated_at' => now('America/Lima'),
            ]);
        } catch (\Throwable $error) {
            $storedError = str_contains($error->getMessage(), 'invalid_grant')
                ? 'AUTH_EXPIRED' : mb_substr($error->getMessage(), 0, 500);
            DB::table('evidencias_drive_instalacion')->where($key)->update([
                'estado' => 'PENDIENTE', 'error' => $storedError,
                'updated_at' => now('America/Lima'),
            ]);
            Log::warning('Evidencia pendiente de confirmar en Drive', ['pedido_id' => $orderId, 'tipo' => $type,
                'evidencia_id' => $evidenceId, 'error' => $error->getMessage()]);
            return false;
        }
        app(InstallationCompletionService::class)->tryCompleteAfterDriveSync($orderId);
        return true;
    }

    public function syncVerifiedContract(int $orderId): bool
    {
        $contract = DB::table('pedidos_instalacion as p')->join('contratos as c', 'c.contrato_id', '=', 'p.contrato_id')
            ->where('p.pedido_id', $orderId)->select('c.contrato_id', 'c.codigo', 'c.firma_revision', 'c.pdf_contrato_ruta')->first();
        if (! $contract || $contract->firma_revision !== 'VERIFICADA' || ! $contract->pdf_contrato_ruta
            || ! Storage::disk('local')->exists($contract->pdf_contrato_ruta)) return false;
        return $this->sync($orderId, 'CONTRATO_VALIDADO', (int) $contract->contrato_id,
            $contract->pdf_contrato_ruta, $contract->codigo.'-firmado.pdf', 'application/pdf');
    }

    public function organizeConfirmed(?int $orderId = null): array
    {
        if (! $this->configured()) throw new \RuntimeException('Configura primero la carpeta y el acceso a Google Drive.');
        $records = DB::table('evidencias_drive_instalacion')->where('estado', 'CONFIRMADO')
            ->whereNotNull('drive_file_id')->when($orderId, fn ($query) => $query->where('pedido_id', $orderId))->get();
        $result = ['moved' => 0, 'already_organized' => 0, 'failed' => 0];
        if ($records->isEmpty()) return $result;
        $token = $this->token();
        foreach ($records as $record) {
            try {
                $folderId = $this->evidenceFolder($token, (int) $record->pedido_id, $record->tipo);
                $file = $this->file($token, $record->drive_file_id);
                $file->throw();
                $parents = $file->json('parents') ?? [];
                if ($file->json('trashed')) throw new \RuntimeException('El archivo está en la papelera de Drive.');
                if (in_array($folderId, $parents, true)) {
                    $result['already_organized']++;
                } elseif (in_array(config('services.installation_drive.folder_id'), $parents, true)) {
                    $this->moveFromRoot($token, $record->drive_file_id, $folderId);
                    $verification = $this->file($token, $record->drive_file_id);
                    $verification->throw();
                    if ($verification->json('trashed') || ! in_array($folderId, $verification->json('parents') ?? [], true)) {
                        throw new \RuntimeException('Drive no confirmó la nueva ubicación del archivo.');
                    }
                    $result['moved']++;
                } else {
                    throw new \RuntimeException('El archivo no está dentro de la carpeta de instalaciones configurada.');
                }
            } catch (\Throwable $error) {
                $result['failed']++;
                Log::warning('No se pudo organizar una evidencia en Drive', [
                    'pedido_id' => $record->pedido_id, 'tipo' => $record->tipo,
                    'evidencia_id' => $record->evidencia_id, 'error' => $error->getMessage(),
                ]);
            }
        }
        return $result;
    }

    private function evidenceFolder(string $token, int $orderId, string $type): string
    {
        $order = DB::table('pedidos_instalacion as p')
            ->leftJoin('clientes as c', 'c.cliente_id', '=', 'p.cliente_id')
            ->where('p.pedido_id', $orderId)
            ->select('p.codigo', 'p.solicitante_nombre', 'c.nombres_razon_social', 'c.apellidos')
            ->first();
        if (! $order) throw new \RuntimeException('No se encontró el pedido de la evidencia.');
        $clientName = trim(implode(' ', array_filter([$order->nombres_razon_social, $order->apellidos])));
        $clientName = trim(preg_replace('/\s+/u', ' ', $clientName ?: ($order->solicitante_nombre ?: '')));
        if ($clientName === '') throw new \RuntimeException('No se encontró el nombre del cliente para organizar la evidencia.');
        $rootFolder = config('services.installation_drive.folder_id');
        $folderName = 'Cliente '.$clientName;
        $orderFolder = $this->findOrderFolder($token, $rootFolder, $orderId)
            ?: $this->findFolder($token, $rootFolder, 'Pedido '.$order->codigo);
        if ($orderFolder) {
            Http::withToken($token)->patch('https://www.googleapis.com/drive/v3/files/'.rawurlencode($orderFolder), [
                'name' => $folderName, 'appProperties' => ['pedido_id' => (string) $orderId],
            ])->throw();
        } else {
            $created = Http::withToken($token)->post('https://www.googleapis.com/drive/v3/files?fields=id', [
                'name' => $folderName, 'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$rootFolder], 'appProperties' => ['pedido_id' => (string) $orderId],
            ]);
            $created->throw();
            $orderFolder = $created->json('id');
            if (! is_string($orderFolder) || $orderFolder === '') {
                throw new \RuntimeException('Drive no devolvió el ID de la carpeta del cliente.');
            }
        }
        $section = match ($type) {
            'FOTO' => 'Fotos de la instalación',
            'CLIENTE' => 'Comprobante con el cliente',
            'CONTRATO' => 'Archivos de campo anteriores',
            'CONTRATO_VALIDADO' => 'Contrato firmado',
            default => throw new \RuntimeException('Tipo de evidencia no reconocido.'),
        };
        return $this->findOrCreateFolder($token, $orderFolder, $section);
    }

    private function findOrCreateFolder(string $token, string $parentId, string $name): string
    {
        $id = $this->findFolder($token, $parentId, $name);
        if ($id) return $id;

        $created = Http::withToken($token)->post('https://www.googleapis.com/drive/v3/files?fields=id', [
            'name' => $name, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$parentId],
        ]);
        $created->throw();
        $id = $created->json('id');
        if (! is_string($id) || $id === '') throw new \RuntimeException('Drive no devolvió el ID de la carpeta creada.');
        return $id;
    }

    private function findFolder(string $token, string $parentId, string $name): ?string
    {
        $quotedName = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);
        $quotedParent = str_replace(['\\', "'"], ['\\\\', "\\'"], $parentId);
        $response = Http::withToken($token)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => "'{$quotedParent}' in parents and name = '{$quotedName}' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'fields' => 'files(id,name,parents),nextPageToken', 'pageSize' => 2,
        ]);
        $response->throw();
        $id = $response->json('files.0.id');
        return is_string($id) && $id !== '' ? $id : null;
    }

    private function findOrderFolder(string $token, string $parentId, int $orderId): ?string
    {
        $quotedParent = str_replace(['\\', "'"], ['\\\\', "\\'"], $parentId);
        $response = Http::withToken($token)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => "'{$quotedParent}' in parents and appProperties has { key='pedido_id' and value='{$orderId}' } and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'fields' => 'files(id),nextPageToken', 'pageSize' => 2,
        ]);
        $response->throw();
        $id = $response->json('files.0.id');
        return is_string($id) && $id !== '' ? $id : null;
    }

    private function file(string $token, string $fileId): \Illuminate\Http\Client\Response
    {
        return Http::withToken($token)->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId), [
            'fields' => 'id,parents,trashed',
        ]);
    }

    private function moveFromRoot(string $token, string $fileId, string $folderId): void
    {
        $query = http_build_query([
            'addParents' => $folderId, 'removeParents' => config('services.installation_drive.folder_id'),
            'fields' => 'id,parents',
        ]);
        $response = Http::withToken($token)->patch('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?'.$query, []);
        $response->throw();
    }

    private function token(): string
    {
        if (filled(config('services.installation_drive.refresh_token'))) {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.installation_drive.client_id'),
                'client_secret' => config('services.installation_drive.client_secret'),
                'refresh_token' => config('services.installation_drive.refresh_token'),
                'grant_type' => 'refresh_token',
            ]);
            $response->throw();
            $token = $response->json('access_token');
            if (! is_string($token) || $token === '') throw new \RuntimeException('Google no devolvió un token de acceso.');
            return $token;
        }
        $credentials = json_decode(file_get_contents(config('services.installation_drive.credentials_path')), true, 512, JSON_THROW_ON_ERROR);
        if (($credentials['type'] ?? null) !== 'service_account' || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new \RuntimeException('Las credenciales de Google Drive no son una cuenta de servicio válida.');
        }
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $issued = time();
        $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $issued, 'exp' => $issued + 3600,
        ], JSON_THROW_ON_ERROR));
        if (! openssl_sign($header.'.'.$payload, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('No se pudo firmar la solicitud de Drive.');
        }
        $assertion = $header.'.'.$payload.'.'.$encode($signature);
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion,
        ]);
        $response->throw();
        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') throw new \RuntimeException('Google no devolvió un token de acceso.');
        return $token;
    }
}
