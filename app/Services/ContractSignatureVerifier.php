<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class ContractSignatureVerifier
{
    public function __construct(private ContractPdf $pdf) {}

    public function verify(object $contract, string $storedPath): array
    {
        $draft = tempnam(sys_get_temp_dir(), 'contract-draft-');
        if ($draft === false) {
            throw ValidationException::withMessages(['documento_pdf' => 'No se pudo preparar la verificación del contrato.']);
        }

        try {
            // The issued draft always says PENDIENTE, even when replacing a file later.
            $contract->estado = 'PENDIENTE';
            file_put_contents($draft, $this->pdf->render($contract));
            $process = new Process([
                config('contracts.signature_python'),
                base_path('scripts/verify_contract_signature.py'),
                $draft,
                Storage::disk('local')->path($storedPath),
            ]);
            $process->setTimeout(30);
            $process->run();
            $result = json_decode($process->getOutput(), true);
            if (! $process->isSuccessful() || ! is_array($result) || ! in_array($result['status'] ?? null, ['signed', 'blank', 'invalid'], true)) {
                logger()->warning('Falló el verificador local de contratos', ['exit' => $process->getExitCode(), 'stderr' => $process->getErrorOutput(), 'stdout' => $process->getOutput()]);
                throw ValidationException::withMessages(['documento_pdf' => 'No se pudo ejecutar el verificador local. Revisa SIGNATURE_PYTHON y sus dependencias.']);
            }

            return $result;
        } finally {
            @unlink($draft);
        }
    }
}
