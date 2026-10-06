<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\GeminiService;

echo "🧪 Probando conexión con Gemini AI...\n\n";

$gemini = new GeminiService();

if (!$gemini->isConfigured()) {
    echo "❌ ERROR: Gemini no está configurado correctamente.\n";
    exit(1);
}

echo "✅ Gemini está configurado\n";
echo "📡 Enviando consulta de prueba...\n\n";

try {
    $result = $gemini->query("¿Cuántos clientes activos tenemos?");
    
    if ($result['success']) {
        echo "✅ ¡Respuesta exitosa!\n\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo $result['response'] . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "Fuente: " . $result['source'] . "\n";
    } else {
        echo "❌ Error:\n";
        echo $result['response'] . "\n";
        if (isset($result['error'])) {
            echo "\n" . $result['error'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
}
