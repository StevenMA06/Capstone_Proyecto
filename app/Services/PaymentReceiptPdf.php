<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

class PaymentReceiptPdf
{
    /**
     * Generar comprobante de pago como HTML (RF-PAG-09)
     * Nota: Para generar PDF real, instalar: composer require barryvdh/laravel-dompdf
     */
    public function generate(Payment $payment): string
    {
        // Cargar relaciones necesarias
        $payment->load(['contract.client', 'contract.plan', 'registeredBy']);

        // Generar nombre único para el archivo
        $filename = 'comprobante_pago_' . $payment->codigo . '_' . now()->format('YmdHis') . '.html';
        
        // Datos para la vista
        $data = [
            'payment' => $payment,
            'contract' => $payment->contract,
            'client' => $payment->contract->client,
            'plan' => $payment->contract->plan,
            'generated_at' => now(),
            'logo_path' => asset('images/lunnys-peru.png'),
        ];

        // Generar HTML
        $html = view('payments.receipt-pdf', $data)->render();

        // Guardar en storage
        $path = 'comprobantes/' . $filename;
        Storage::disk('public')->put($path, $html);

        // Retornar ruta relativa
        return 'storage/' . $path;
    }

    /**
     * Verificar si existe el comprobante
     */
    public function exists(string $path): bool
    {
        // Remover 'storage/' del inicio si existe
        $storagePath = str_replace('storage/', '', $path);
        
        return Storage::disk('public')->exists($storagePath);
    }

    /**
     * Obtener la ruta completa del comprobante
     */
    public function getFullPath(string $path): string
    {
        return public_path($path);
    }
}
