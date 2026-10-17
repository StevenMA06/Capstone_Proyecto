# Script para ejecutar ciclos múltiples de pruebas de IA
# Uso: .\scripts\run-ia-cycles.ps1 [cantidad_ciclos]

param(
    [int]$Cycles = 10
)

$ErrorActionPreference = "Continue"
$startTime = Get-Date
$results = @()

Write-Host "===========================================" -ForegroundColor Cyan
Write-Host "Ejecutando $Cycles ciclos de pruebas CP-IA" -ForegroundColor Cyan
Write-Host "===========================================" -ForegroundColor Cyan
Write-Host ""

for ($i = 1; $i -le $Cycles; $i++) {
    Write-Host "--- Ciclo $i de $Cycles ---" -ForegroundColor Yellow
    $cycleStart = Get-Date
    
    # Ejecutar las pruebas de IA
    $output = npm run e2e -- --grep "CP-IA" 2>&1 | Out-String
    $exitCode = $LASTEXITCODE
    
    $cycleEnd = Get-Date
    $duration = ($cycleEnd - $cycleStart).TotalSeconds
    
    # Determinar resultado
    $status = if ($exitCode -eq 0) { "PASS" } else { "FAIL" }
    $statusColor = if ($exitCode -eq 0) { "Green" } else { "Red" }
    
    # Extraer información del último run
    $lastRun = Get-ChildItem "artifacts\e2e" -Directory | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    $runId = $lastRun.Name
    
    # Guardar resultado
    $results += [PSCustomObject]@{
        Ciclo = $i
        Status = $status
        Duracion = [math]::Round($duration, 2)
        RunId = $runId
        ExitCode = $exitCode
    }
    
    Write-Host "Ciclo ${i}: $status ($duration s) - Run: $runId" -ForegroundColor $statusColor
    Write-Host ""
    
    # Pausa breve entre ciclos para evitar problemas de servidor
    if ($i -lt $Cycles) {
        Start-Sleep -Seconds 2
    }
}

$endTime = Get-Date
$totalDuration = ($endTime - $startTime).TotalMinutes

Write-Host "===========================================" -ForegroundColor Cyan
Write-Host "RESUMEN DE CICLOS" -ForegroundColor Cyan
Write-Host "===========================================" -ForegroundColor Cyan
Write-Host ""

# Mostrar tabla de resultados
$results | Format-Table -AutoSize

# Estadísticas
$passed = ($results | Where-Object { $_.Status -eq "PASS" }).Count
$failed = ($results | Where-Object { $_.Status -eq "FAIL" }).Count
$passRate = [math]::Round(($passed / $Cycles) * 100, 2)

Write-Host "Total de ciclos: $Cycles" -ForegroundColor White
Write-Host "Exitosos: $passed" -ForegroundColor Green
Write-Host "Fallidos: $failed" -ForegroundColor Red
Write-Host "Tasa de éxito: ${passRate}%" -ForegroundColor $(if ($passRate -ge 90) { "Green" } elseif ($passRate -ge 70) { "Yellow" } else { "Red" })
Write-Host "Duración total: $([math]::Round($totalDuration, 2)) minutos" -ForegroundColor White
Write-Host ""

# Guardar reporte JSON
$report = @{
    ejecutado_en = $startTime.ToString("yyyy-MM-dd HH:mm:ss")
    ciclos_totales = $Cycles
    ciclos_exitosos = $passed
    ciclos_fallidos = $failed
    tasa_exito_pct = $passRate
    duracion_total_min = [math]::Round($totalDuration, 2)
    duracion_promedio_seg = [math]::Round(($results | Measure-Object -Property Duracion -Average).Average, 2)
    resultados = $results
}

$reportPath = "artifacts\e2e\ciclos-ia-$(Get-Date -Format 'yyyyMMdd-HHmmss').json"
$report | ConvertTo-Json -Depth 5 | Set-Content $reportPath -Encoding UTF8

Write-Host "Reporte guardado en: $reportPath" -ForegroundColor Cyan
Write-Host ""

# Exit code: 0 si todos pasaron, 1 si hubo fallos
if ($failed -gt 0) {
    exit 1
} else {
    exit 0
}
