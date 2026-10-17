# Suite completa de pruebas de hardware para Lunnys Perú
# Combina: Apache Bench + Monitoreo + Tests E2E

param(
    [switch]$SkipE2E = $false,
    [int]$LoadTestRequests = 0,      # 0 = autodetectar desde perfil
    [int]$LoadTestConcurrency = 0,   # 0 = autodetectar desde perfil
    [switch]$AutoDetect = $true      # Detectar hardware automáticamente
)

$ErrorActionPreference = "Stop"

# ============================================
# AUTO-DETECCIÓN DE HARDWARE (si está habilitada)
# ============================================
if ($AutoDetect) {
    Write-Host "🔍 Auto-detectando perfil de hardware..." -ForegroundColor Cyan
    
    # Ejecutar detección si no existe el archivo de perfil o si tiene más de 1 día
    $profileFile = ".hardware-profile"
    $needsDetection = $true
    
    if (Test-Path $profileFile) {
        $profileAge = (Get-Date) - (Get-Item $profileFile).LastWriteTime
        if ($profileAge.TotalHours -lt 24) {
            $needsDetection = $false
            Write-Host "✅ Usando perfil detectado previamente (hace $([math]::Round($profileAge.TotalHours, 1)) horas)" -ForegroundColor Green
        }
    }
    
    if ($needsDetection) {
        & "$PSScriptRoot\detect-hardware-profile.ps1" | Out-Null
    }
    
    # Cargar configuración del perfil
    if (Test-Path $profileFile) {
        $activeProfile = Get-Content $profileFile -Raw | ConvertFrom-Json
        
        Write-Host "📊 Perfil activo: $($activeProfile.config.name)" -ForegroundColor Cyan
        
        # Usar configuración del perfil si no se especificaron parámetros
        if ($LoadTestRequests -eq 0) {
            $LoadTestRequests = $activeProfile.config.test_config.apache_bench.requests
        }
        if ($LoadTestConcurrency -eq 0) {
            $LoadTestConcurrency = $activeProfile.config.test_config.apache_bench.concurrency
        }
        
        Write-Host "  ⚙️  Apache Bench: $LoadTestRequests peticiones, $LoadTestConcurrency concurrentes" -ForegroundColor Gray
        Write-Host "  ⚙️  Playwright: $($activeProfile.config.test_config.playwright.workers) workers" -ForegroundColor Gray
    } else {
        Write-Host "⚠️  No se pudo cargar perfil, usando valores por defecto" -ForegroundColor Yellow
        if ($LoadTestRequests -eq 0) { $LoadTestRequests = 200 }
        if ($LoadTestConcurrency -eq 0) { $LoadTestConcurrency = 20 }
    }
    
    Start-Sleep -Seconds 2
}

Write-Host @"

╔════════════════════════════════════════════════════════════╗
║  SUITE DE PRUEBAS DE HARDWARE - LUNNYS PERÚ               ║
║  Valida que el hardware cumple requisitos mínimos         ║
╚════════════════════════════════════════════════════════════╝

"@ -ForegroundColor Cyan

# ============================================
# 1. PRE-VERIFICACIONES
# ============================================
Write-Host "[1/6] Verificaciones previas..." -ForegroundColor Yellow

# Verificar que estamos en el directorio del proyecto
if (-Not (Test-Path "composer.json")) {
    Write-Host "❌ Ejecuta este script desde la raíz del proyecto" -ForegroundColor Red
    exit 1
}

# Verificar servidor Laravel
try {
    $response = Invoke-WebRequest -Uri "http://127.0.0.1:8000" -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
    Write-Host "  ✅ Servidor Laravel activo (HTTP $($response.StatusCode))" -ForegroundColor Green
}
catch {
    Write-Host "  ❌ Servidor Laravel no responde en http://127.0.0.1:8000" -ForegroundColor Red
    Write-Host "     Ejecuta en otra terminal: php artisan serve" -ForegroundColor Yellow
    exit 1
}

# Verificar MySQL/MariaDB
try {
    php artisan migrate:status | Out-Null
    Write-Host "  ✅ Base de datos conectada" -ForegroundColor Green
}
catch {
    Write-Host "  ❌ No se puede conectar a la base de datos" -ForegroundColor Red
    Write-Host "     Verifica que MySQL esté corriendo en XAMPP" -ForegroundColor Yellow
    exit 1
}

# Verificar Apache Bench
$abPath = "C:\xampp\apache\bin\ab.exe"
if (-Not (Test-Path $abPath)) {
    Write-Host "  ⚠️  Apache Bench (ab.exe) no encontrado, se omitirá test de carga HTTP" -ForegroundColor Yellow
    $skipAB = $true
} else {
    Write-Host "  ✅ Apache Bench disponible" -ForegroundColor Green
    $skipAB = $false
}

# Verificar Playwright (solo si no se salta E2E)
if (-Not $SkipE2E) {
    if (-Not (Test-Path "node_modules/@playwright")) {
        Write-Host "  ⚠️  Playwright no instalado, se omitirán tests E2E" -ForegroundColor Yellow
        Write-Host "     Ejecuta: npm install && npm run e2e:install" -ForegroundColor Yellow
        $SkipE2E = $true
    } else {
        Write-Host "  ✅ Playwright instalado" -ForegroundColor Green
    }
}

Start-Sleep -Seconds 2

# ============================================
# 2. SNAPSHOT INICIAL DEL SISTEMA
# ============================================
Write-Host "`n[2/6] Capturando estado inicial del sistema..." -ForegroundColor Yellow

$initialCpu = (Get-Counter '\Processor(_Total)\% Processor Time').CounterSamples.CookedValue
$initialRam = (Get-Counter '\Memory\Available MBytes').CounterSamples.CookedValue
$totalRam = (Get-CimInstance Win32_OperatingSystem).TotalVisibleMemorySize / 1KB

Write-Host "  CPU actual: $([math]::Round($initialCpu, 1))%" -ForegroundColor White
Write-Host "  RAM disponible: $([math]::Round($initialRam, 0)) MB / $([math]::Round($totalRam, 0)) MB" -ForegroundColor White

if ($initialCpu -gt 50) {
    Write-Host "  ⚠️  CPU ya está al $([math]::Round($initialCpu, 1))% antes de empezar" -ForegroundColor Yellow
    Write-Host "     Cierra aplicaciones en segundo plano para mejores resultados" -ForegroundColor Yellow
}

if ($initialRam -lt 2048) {
    Write-Host "  ⚠️  Poca RAM disponible ($([math]::Round($initialRam, 0)) MB)" -ForegroundColor Yellow
    Write-Host "     Los tests pueden ser más lentos" -ForegroundColor Yellow
}

Start-Sleep -Seconds 2

# ============================================
# 3. INICIAR MONITOREO EN SEGUNDO PLANO
# ============================================
Write-Host "`n[3/6] Iniciando monitoreo de hardware..." -ForegroundColor Yellow

$monitorDuration = 180  # 3 minutos
if ($SkipE2E -and $skipAB) {
    $monitorDuration = 60  # Solo 1 minuto si no hay tests pesados
}

$monitorJob = Start-Job -ScriptBlock {
    param($duration, $interval)
    & "$using:PWD\scripts\monitor-hardware.ps1" -DurationSeconds $duration -IntervalSeconds $interval
} -ArgumentList $monitorDuration, 5

Write-Host "  ✅ Monitoreo iniciado por $monitorDuration segundos (Job ID: $($monitorJob.Id))" -ForegroundColor Green

Start-Sleep -Seconds 3

# ============================================
# 4. TEST DE CARGA CON APACHE BENCH
# ============================================
if (-Not $skipAB) {
    Write-Host "`n[4/6] Ejecutando test de carga HTTP con Apache Bench..." -ForegroundColor Yellow
    Write-Host "  Configuración: $LoadTestRequests peticiones, $LoadTestConcurrency concurrentes`n" -ForegroundColor White
    
    try {
        & "$PSScriptRoot\apache-bench-test.ps1" -Requests $LoadTestRequests -Concurrency $LoadTestConcurrency
    }
    catch {
        Write-Host "  ⚠️  Error en Apache Bench: $_" -ForegroundColor Yellow
    }
    
    Start-Sleep -Seconds 2
} else {
    Write-Host "`n[4/6] Test de carga HTTP omitido" -ForegroundColor Gray
}

# ============================================
# 5. TESTS E2E CON PLAYWRIGHT
# ============================================
if (-Not $SkipE2E) {
    Write-Host "`n[5/6] Ejecutando tests E2E con Playwright..." -ForegroundColor Yellow
    Write-Host "  Esto puede tomar varios minutos...`n" -ForegroundColor White
    
    try {
        npm run e2e
        Write-Host "`n  ✅ Tests E2E completados" -ForegroundColor Green
    }
    catch {
        Write-Host "`n  ⚠️  Algunos tests E2E fallaron (revisa logs arriba)" -ForegroundColor Yellow
    }
} else {
    Write-Host "`n[5/6] Tests E2E omitidos (usa sin -SkipE2E para incluirlos)" -ForegroundColor Gray
}

# ============================================
# 6. FINALIZAR MONITOREO Y ANALIZAR
# ============================================
Write-Host "`n[6/6] Finalizando monitoreo y analizando resultados..." -ForegroundColor Yellow

# Esperar a que termine el monitoreo
Wait-Job -Job $monitorJob | Out-Null
$monitorOutput = Receive-Job -Job $monitorJob
Remove-Job -Job $monitorJob

# Mostrar output del monitoreo
Write-Host $monitorOutput

# ============================================
# RESUMEN FINAL
# ============================================
Write-Host @"

╔════════════════════════════════════════════════════════════╗
║  RESUMEN DE PRUEBAS                                        ║
╚════════════════════════════════════════════════════════════╝

"@ -ForegroundColor Cyan

# Snapshot final
$finalCpu = (Get-Counter '\Processor(_Total)\% Processor Time').CounterSamples.CookedValue
$finalRam = (Get-Counter '\Memory\Available MBytes').CounterSamples.CookedValue

Write-Host "Estado del sistema:" -ForegroundColor White
Write-Host "  CPU:  Inicial $([math]::Round($initialCpu, 1))% → Final $([math]::Round($finalCpu, 1))%" -ForegroundColor White
Write-Host "  RAM:  Inicial $([math]::Round($initialRam, 0)) MB → Final $([math]::Round($finalRam, 0)) MB disponible`n" -ForegroundColor White

# Recomendaciones
Write-Host "Recomendaciones:" -ForegroundColor Cyan

$needsUpgrade = $false

# Analizar resultados de AB (si existe el resumen más reciente)
if (-Not $skipAB) {
    $latestSummary = Get-ChildItem "stress-test-results\summary-*.txt" -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if ($latestSummary) {
        $summaryContent = Get-Content $latestSummary.FullName -Raw
        if ($summaryContent -match "([0-9.]+) req/s") {
            $avgRps = [double]$matches[1]
            if ($avgRps -lt 20) {
                Write-Host "  ⚠️  Rendimiento HTTP bajo (<20 req/s) - Considera upgrade de CPU" -ForegroundColor Yellow
                $needsUpgrade = $true
            } elseif ($avgRps -lt 50) {
                Write-Host "  ⚠️  Rendimiento HTTP límite ($avgRps req/s) - Hardware justo" -ForegroundColor Yellow
            } else {
                Write-Host "  ✅ Rendimiento HTTP bueno ($avgRps req/s)" -ForegroundColor Green
            }
        }
    }
}

# Analizar RAM
$ramUsedPercent = (($totalRam - $finalRam) / $totalRam) * 100
if ($ramUsedPercent -gt 90) {
    Write-Host "  ⚠️  Uso de RAM alto (>90%) - Considera agregar más RAM" -ForegroundColor Yellow
    $needsUpgrade = $true
} elseif ($ramUsedPercent -gt 80) {
    Write-Host "  ⚠️  Uso de RAM elevado (~$([math]::Round($ramUsedPercent, 0))%) - Monitorear" -ForegroundColor Yellow
} else {
    Write-Host "  ✅ Uso de RAM aceptable ($([math]::Round($ramUsedPercent, 0))%)" -ForegroundColor Green
}

# Conclusión final
Write-Host ""
if ($needsUpgrade) {
    Write-Host "🔴 CONCLUSIÓN: Hardware por debajo del mínimo recomendado" -ForegroundColor Red
    Write-Host "   El sistema puede funcionar pero con limitaciones de rendimiento." -ForegroundColor Yellow
    Write-Host "   Revisa la documentación en docs/hardware-stress-testing.md" -ForegroundColor Yellow
} else {
    Write-Host "🟢 CONCLUSIÓN: Hardware cumple con los requisitos" -ForegroundColor Green
    Write-Host "   El sistema debería funcionar sin problemas de rendimiento." -ForegroundColor White
}

Write-Host "`nResultados detallados:" -ForegroundColor Cyan
Write-Host "  - Métricas hardware: hardware-metrics-*.csv" -ForegroundColor White
if (-Not $skipAB) {
    Write-Host "  - Tests de carga HTTP: stress-test-results\" -ForegroundColor White
}
if (-Not $SkipE2E) {
    Write-Host "  - Tests E2E: (ver output arriba)" -ForegroundColor White
}

Write-Host "`n✅ Suite de pruebas completada" -ForegroundColor Green
