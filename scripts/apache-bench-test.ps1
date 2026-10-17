# Script de pruebas de carga con Apache Bench (ab)
# Viene incluido con XAMPP

param(
    [string]$BaseURL = "http://127.0.0.1:8000",
    [int]$Requests = 100,
    [int]$Concurrency = 10
)

Write-Host "`n=== Apache Bench Load Test ===" -ForegroundColor Cyan
Write-Host "URL: $BaseURL" -ForegroundColor White
Write-Host "Peticiones: $Requests" -ForegroundColor White
Write-Host "Concurrencia: $Concurrency`n" -ForegroundColor White

# Verificar que ab.exe existe
$abPath = "C:\xampp\apache\bin\ab.exe"
if (-Not (Test-Path $abPath)) {
    Write-Host "❌ ab.exe no encontrado en: $abPath" -ForegroundColor Red
    Write-Host "   Verifica que XAMPP esté instalado en C:\xampp" -ForegroundColor Yellow
    exit 1
}

# Verificar que el servidor responde
try {
    $testResponse = Invoke-WebRequest -Uri $BaseURL -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
    Write-Host "✅ Servidor responde (HTTP $($testResponse.StatusCode))`n" -ForegroundColor Green
}
catch {
    Write-Host "❌ Servidor no responde en $BaseURL" -ForegroundColor Red
    Write-Host "   Ejecuta: php artisan serve" -ForegroundColor Yellow
    exit 1
}

# Array de endpoints a probar
$endpoints = @(
    @{ Path = "/"; Name = "Home/Login" },
    @{ Path = "/dashboard"; Name = "Dashboard" },
    @{ Path = "/clientes"; Name = "Clientes" },
    @{ Path = "/contratos"; Name = "Contratos" }
)

$resultadosDir = "stress-test-results"
if (-Not (Test-Path $resultadosDir)) {
    New-Item -ItemType Directory -Path $resultadosDir | Out-Null
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$summaryFile = "$resultadosDir\summary-$timestamp.txt"

"=== RESUMEN DE PRUEBAS DE CARGA ===" | Out-File $summaryFile
"Fecha: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" | Out-File $summaryFile -Append
"Peticiones: $Requests | Concurrencia: $Concurrency" | Out-File $summaryFile -Append
"" | Out-File $summaryFile -Append

foreach ($endpoint in $endpoints) {
    $url = "$BaseURL$($endpoint.Path)"
    $outputFile = "$resultadosDir\ab-$($endpoint.Name.Replace('/', '-'))-$timestamp.txt"
    
    Write-Host "Probando: $($endpoint.Name) ($url)" -ForegroundColor Yellow
    
    # Ejecutar ab
    & $abPath -n $Requests -c $Concurrency -g "$resultadosDir\gnuplot-$($endpoint.Name.Replace('/', '-'))-$timestamp.tsv" $url > $outputFile 2>&1
    
    # Extraer métricas clave
    $content = Get-Content $outputFile -Raw
    
    if ($content -match "Requests per second:\s+([0-9.]+)") {
        $rps = [math]::Round([double]$matches[1], 2)
    }
    if ($content -match "Time per request:\s+([0-9.]+) \[ms\] \(mean\)") {
        $meanTime = [math]::Round([double]$matches[1], 2)
    }
    if ($content -match "Failed requests:\s+(\d+)") {
        $failed = $matches[1]
    }
    
    # Color coding
    $rpsColor = if ($rps -ge 50) { "Green" } elseif ($rps -ge 20) { "Yellow" } else { "Red" }
    $timeColor = if ($meanTime -le 200) { "Green" } elseif ($meanTime -le 500) { "Yellow" } else { "Red" }
    $failColor = if ($failed -eq 0) { "Green" } else { "Red" }
    
    Write-Host "  └─ Req/s: " -NoNewline
    Write-Host "$rps " -NoNewline -ForegroundColor $rpsColor
    Write-Host "| Tiempo promedio: " -NoNewline
    Write-Host "${meanTime}ms " -NoNewline -ForegroundColor $timeColor
    Write-Host "| Fallos: " -NoNewline
    Write-Host "$failed`n" -ForegroundColor $failColor
    
    # Guardar en resumen
    "$($endpoint.Name): $rps req/s | ${meanTime}ms promedio | $failed fallos" | Out-File $summaryFile -Append
}

Write-Host "=== PRUEBA COMPLETADA ===" -ForegroundColor Cyan
Write-Host "Resultados guardados en: $resultadosDir\" -ForegroundColor Green
Write-Host "Resumen: $summaryFile`n" -ForegroundColor Green

# Mostrar resumen
Get-Content $summaryFile | ForEach-Object { Write-Host $_ }

Write-Host "`n=== Interpretación ===" -ForegroundColor Cyan
Write-Host "✅ Req/s > 50        = Hardware suficiente" -ForegroundColor Green
Write-Host "⚠️  Req/s 20-50     = Hardware límite" -ForegroundColor Yellow
Write-Host "❌ Req/s < 20       = Hardware insuficiente" -ForegroundColor Red
Write-Host "✅ Tiempo < 200ms   = Respuesta rápida" -ForegroundColor Green
Write-Host "⚠️  Tiempo 200-500ms = Respuesta aceptable" -ForegroundColor Yellow
Write-Host "❌ Tiempo > 500ms   = Respuesta lenta" -ForegroundColor Red
