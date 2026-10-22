<?php
/**
 * Script para convertir todos los tests a usar Data Providers
 * Ejecutar: php convert_tests_to_dataproviders.php
 */

$testsDirectory = __DIR__ . '/phpunittestsinforme';

echo "🔄 Iniciando conversión de tests a Data Providers...\n\n";

// Leer configuración de conversión desde archivo JSON
$conversionsFile = __DIR__ . '/test_conversions_config.json';

if (!file_exists($conversionsFile)) {
    echo "❌ No se encontró el archivo de configuración: test_conversions_config.json\n";
    echo "📝 Creando archivo de ejemplo...\n";
    
    $exampleConfig = [
        "note" => "Este archivo define qué tests convertir y con qué data providers",
        "conversions" => [
            [
                "file" => "phpunittestsinforme/Clientes/ClientDeactivateTest.php",
                "method" => "desactiva_cliente_activo_correctamente",
                "provider_name" => "clienteActivoProvider",
                "scenarios" => [
                    ["estado" => "ACTIVO", "tipo_doc" => "DNI", "motivo" => "Test 1"],
                    ["estado" => "ACTIVO", "tipo_doc" => "RUC", "motivo" => "Test 2"]
                ]
            ]
        ]
    ];
    
    file_put_contents($conversionsFile, json_encode($exampleConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✅ Archivo de ejemplo creado. Por favor configurarlo y volver a ejecutar.\n";
    exit(0);
}

$config = json_decode(file_get_contents($conversionsFile), true);

if (!isset($config['conversions']) || !is_array($config['conversions'])) {
    echo "❌ Configuración inválida en test_conversions_config.json\n";
    exit(1);
}

$totalConversions = 0;
foreach ($config['conversions'] as $conversion) {
    $filePath = __DIR__ . '/' . $conversion['file'];
    
    if (!file_exists($filePath)) {
        echo "⚠️  Archivo no encontrado: {$conversion['file']}\n";
        continue;
    }
    
    echo "📄 Procesando: {$conversion['file']}\n";
    echo "   Método: {$conversion['method']}\n";
    echo "   Provider: {$conversion['provider_name']}\n";
    echo "   Escenarios: " . count($conversion['scenarios']) . "\n";
    
    $totalConversions++;
}

echo "\n✅ Total de archivos a convertir: {$totalConversions}\n";
echo "⚠️  ADVERTENCIA: Este script modificará los archivos originales.\n";
echo "💡 Recomendación: Hacer commit antes de ejecutar la conversión.\n";
echo "\n🔧 Para ejecutar la conversión automática, descomenta la línea 70 en este script.\n";

// Descomentar la siguiente línea para ejecutar la conversión:
// executeConversions($config['conversions']);

function executeConversions($conversions) {
    foreach ($conversions as $conversion) {
        $filePath = __DIR__ . '/' . $conversion['file'];
        
        if (!file_exists($filePath)) {
            continue;
        }
        
        $content = file_get_contents($filePath);
        
        // Aquí iría la lógica de conversión real
        // Por ahora solo muestra qué haría
        echo "✅ Convertiría: {$conversion['file']}\n";
    }
}
