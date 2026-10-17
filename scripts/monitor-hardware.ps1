# Script de monitoreo continuo de hardware durante pruebas de carga
# Uso: .\scripts\monitor-hardware.ps1 -DurationSeconds 120 -IntervalSeconds 5

param(
    [int]$DurationSeconds = 60,
    [int]$IntervalSeconds = 5
)

$outputFile = "hardware-metrics-$(Get-Date -Format 'yyyyMMdd-HHmmss').csv"
$iterations = [math]::Ceiling($DurationSeconds / $IntervalSeconds)

"Timestamp,CPU_%,RAM_Available_MB,Disk_Read_MB/s,Disk_Write_MB/s,Network_Mbps" | Out-File $outputFile

Write-Host "`n=== Monitoreo de Hardware ===" -ForegroundColor Cyan
Write-Host "Duración: $DurationSeconds segundos" -ForegroundColor White
Write-Host "Intervalo: $IntervalSeconds segundos" -ForegroundColor White
Write-Host "Archivo: $outputFile`n" -ForegroundColor White

$maxCpu = 0
$minRam = [double]::MaxValue
$avgCpu = 0

for ($i = 0; $i -lt $iterations; $i++) {
    try {
        $cpu = (Get-Counter '\Processor(_Total)\% Processor Time').CounterSamples.CookedValue
        $ram = (Get-Counter '\Memory\Available MBytes').CounterSamples.CookedValue
        $diskRead = (Get-Counter '\PhysicalDisk(_Total)\Disk Read Bytes/sec').CounterSamples.CookedValue / 1MB
        $diskWrite = (Get-Counter '\PhysicalDisk(_Total)\Disk Write Bytes/sec').CounterSamples.CookedValue / 1MB
        
        # Network puede tener múltiples interfaces, sumamos todas
        $networkCounters = Get-Counter '\Network Interface(*)\Bytes Total/sec' -ErrorAction SilentlyContinue
        $networkTotal = 0
        if ($networkCounters) {
            $networkTotal = ($networkCounters.CounterSamples | Measure-Object -Property CookedValue -Sum).Sum / 1MB * 8
        }
        
        $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
        
        # Guardar en CSV
        "$timestamp,$([math]::Round($cpu,2)),$([math]::Round($ram,2)),$([math]::Round($diskRead,2)),$([math]::Round($diskWrite,2)),$([math]::Round($networkTotal,2))" | Out-File $outputFile -Append
        
        # Actualizar estadísticas
        if ($cpu -gt $maxCpu) { $maxCpu = $cpu }
        if ($ram -lt $minRam) { $minRam = $ram }
        $avgCpu += $cpu
        
        # Color coding para alertas
        $cpuColor = "Green"
        if ($cpu -gt 80) { $cpuColor = "Yellow" }
        if ($cpu -gt 90) { $cpuColor = "Red" }
        
        $ramColor = "Green"
        if ($ram -lt 2048) { $ramColor = "Yellow" }
        if ($ram -lt 1024) { $ramColor = "Red" }
        
        Write-Host "[$timestamp] " -NoNewline -ForegroundColor Gray
        Write-Host "CPU: $([math]::Round($cpu,1))%" -NoNewline -ForegroundColor $cpuColor
        Write-Host " | " -NoNewline
        Write-Host "RAM libre: $([math]::Round($ram,0)) MB" -NoNewline -ForegroundColor $ramColor
        Write-Host " | Disco R: $([math]::Round($diskRead,1)) MB/s W: $([math]::Round($diskWrite,1)) MB/s" -ForegroundColor Gray
        
        Start-Sleep -Seconds $IntervalSeconds
    }
    catch {
        Write-Host "⚠️  Error leyendo contadores: $_" -ForegroundColor Yellow
    }
}

# Resumen final
$avgCpu = $avgCpu / $iterations

Write-Host "`n=== Resumen ===" -ForegroundColor Cyan
Write-Host "CPU Máxima:     $([math]::Round($maxCpu,1))%" -ForegroundColor $(if ($maxCpu -gt 90) { "Red" } elseif ($maxCpu -gt 80) { "Yellow" } else { "Green" })
Write-Host "CPU Promedio:   $([math]::Round($avgCpu,1))%" -ForegroundColor White
Write-Host "RAM Mínima:     $([math]::Round($minRam,0)) MB disponible" -ForegroundColor $(if ($minRam -lt 1024) { "Red" } elseif ($minRam -lt 2048) { "Yellow" } else { "Green" })

Write-Host "`n✅ Métricas guardadas en: $outputFile" -ForegroundColor Green
Write-Host "   Abre el archivo con Excel para ver gráficos" -ForegroundColor White

# Diagnóstico automático
Write-Host "`n=== Diagnóstico ===" -ForegroundColor Cyan
if ($maxCpu -gt 95) {
    Write-Host "⚠️  CPU sobrecargada (>95%). Considera:" -ForegroundColor Yellow
    Write-Host "   - Reducir workers de Playwright" -ForegroundColor White
    Write-Host "   - Cerrar aplicaciones en segundo plano" -ForegroundColor White
    Write-Host "   - Upgrade de CPU (más núcleos)" -ForegroundColor White
}
if ($minRam -lt 1024) {
    Write-Host "⚠️  RAM insuficiente (<1 GB libre). Considera:" -ForegroundColor Yellow
    Write-Host "   - Cerrar aplicaciones en segundo plano" -ForegroundColor White
    Write-Host "   - Desactivar videos en Playwright" -ForegroundColor White
    Write-Host "   - Upgrade de RAM" -ForegroundColor White
}
if ($maxCpu -lt 70 -and $minRam -gt 2048) {
    Write-Host "✅ Hardware suficiente para las pruebas" -ForegroundColor Green
}
