<?php

namespace App\Services;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PaymentReceiptPdf
{
    /**
     * Generar comprobante de pago como PDF (RF-PAG-09, RF-PAG-13)
     * 
     * Requiere: composer require barryvdh/laravel-dompdf
     * Configuración en config/dompdf.php (generado por el paquete)
     */
    public function generate(Payment $payment): string
    {
        // Cargar relaciones necesarias
        $payment->load(['contract.client', 'contract.plan', 'registeredBy']);

        // Generar nombre único para el archivo
        $filename = 'comprobante_pago_' . $payment->codigo . '_' . now()->format('YmdHis') . '.pdf';
        
        // Datos para la vista
        $data = [
            'payment' => $payment,
            'contract' => $payment->contract,
            'client' => $payment->contract->client,
            'plan' => $payment->contract->plan,
            'generated_at' => now(),
            'generated_by' => auth()->user()?->nombre_usuario ?? 'Sistema',
            'logo_path' => public_path('img/logo.png'),
        ];

        $pdf = Pdf::loadView('payments.receipt-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'Arial');

        $path = 'comprobantes/' . $filename;
        Storage::disk('public')->put($path, $pdf->output());

        // Retornar ruta relativa
        return 'storage/' . $path;
    }

    /**
     * Generar PDF y retornar directamente para descarga
     * Sin guardar en storage
     */
    public function download(Payment $payment): \Illuminate\Http\Response
    {
        // Cargar relaciones necesarias
        $payment->load(['contract.client', 'contract.plan', 'registeredBy']);

        $filename = 'comprobante_pago_' . $payment->codigo . '.pdf';
        
        // Datos para la vista
        $data = [
            'payment' => $payment,
            'contract' => $payment->contract,
            'client' => $payment->contract->client,
            'plan' => $payment->contract->plan,
            'generated_at' => now(),
            'generated_by' => auth()->user()?->nombre_usuario ?? 'Sistema',
            'logo_path' => public_path('img/logo.png'),
        ];

        $pdf = Pdf::loadView('payments.receipt-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'Arial');

        return $pdf->download($filename);
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
