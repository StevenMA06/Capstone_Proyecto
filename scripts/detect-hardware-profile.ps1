# Script de detección automática de perfil de hardware
# Analiza el sistema y sugiere/aplica la configuración óptima

param(
    [switch]$ShowOnly = $false,  # Solo mostrar detección, no aplicar
    [string]$ForceProfile = ""   # Forzar un perfil específico: minimal, recommended, optimal, enterprise
)

$configPath = "config\hardware-profiles.json"

# ============================================
# FUNCIONES AUXILIARES
# ============================================

function Get-CPUInfo {
    $cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
    return @{
        Name = $cpu.Name
        Cores = $cpu.NumberOfCores
        LogicalProcessors = $cpu.NumberOfLogicalProcessors
        MaxClockSpeed = $cpu.MaxClockSpeedMHz
    }
}

function Get-RAMInfo {
    $os = Get-CimInstance Win32_OperatingSystem
    $totalRamMB = [math]::Round($os.TotalVisibleMemorySize / 1KB, 0)
    $freeRamMB = [math]::Round($os.FreePhysicalMemory / 1KB, 0)
    return @{
        TotalMB = $totalRamMB
        FreeMB = $freeRamMB
        UsedMB = $totalRamMB - $freeRamMB
    }
}

function Get-DiskInfo {
    $systemDrive = $env:SystemDrive
    $disk = Get-PSDrive -Name $systemDrive.Replace(":", "") -ErrorAction SilentlyContinue
    
    if ($disk) {
        $usedGB = [math]::Round($disk.Used / 1GB, 2)
        $freeGB = [math]::Round($disk.Free / 1GB, 2)
        $totalGB = $usedGB + $freeGB
        
        # Detectar tipo de disco (aproximado)
        $diskType = "HDD"
        $physicalDisks = Get-PhysicalDisk -ErrorAction SilentlyContinue
        if ($physicalDisks) {
            $mainDisk = $physicalDisks | Where-Object { $_.DeviceId -eq 0 }
            if ($mainDisk) {
                if ($mainDisk.MediaType -eq "SSD") {
                    $diskType = "SSD"
                }
                if ($mainDisk.BusType -eq "NVMe") {
                    $diskType = "NVMe"
                }
            }
        }
        
        return @{
            Type = $diskType
            TotalGB = $totalGB
            UsedGB = $usedGB
            FreeGB = $freeGB
        }
    }
    
    return @{
        Type = "Unknown"
        TotalGB = 0
        UsedGB = 0
        FreeGB = 0
    }
}

function Detect-ProfileFromCPUName {
    param([string]$cpuName, $profiles)
    
    foreach ($family in $profiles.processor_families.families.PSObject.Properties) {
        $familyData = $family.Value
        foreach ($pattern in $familyData.patterns) {
            if ($cpuName -match $pattern) {
                return $familyData.default_profile
            }
        }
    }
    
    return $null
}

function Detect-ProfileFromSpecs {
    param([int]$cores, [int]$ramMB, $profiles)
    
    foreach ($rule in $profiles.detection_rules.rules) {
        if ($cores -ge $rule.conditions.cpu_cores_min -and $ramMB -ge $rule.conditions.ram_mb_min) {
            return $rule.profile
        }
    }
    
    return "minimal"
}

# ============================================
# DETECCIÓN DE HARDWARE
# ============================================

Write-Host @"

╔════════════════════════════════════════════════════════════╗
║  DETECCIÓN AUTOMÁTICA DE PERFIL DE HARDWARE               ║
╚════════════════════════════════════════════════════════════╝

"@ -ForegroundColor Cyan

# Cargar configuración
if (-Not (Test-Path $configPath)) {
    Write-Host "❌ No se encuentra el archivo de configuración: $configPath" -ForegroundColor Red
    exit 1
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json

# Detectar hardware
Write-Host "Analizando hardware del sistema..." -ForegroundColor Yellow

$cpuInfo = Get-CPUInfo
$ramInfo = Get-RAMInfo
$diskInfo = Get-DiskInfo

Write-Host "`n=== Hardware Detectado ===" -ForegroundColor Cyan
Write-Host "CPU:" -ForegroundColor White
Write-Host "  Modelo:              $($cpuInfo.Name)" -ForegroundColor Gray
Write-Host "  Núcleos físicos:     $($cpuInfo.Cores)" -ForegroundColor Gray
Write-Host "  Procesadores lógicos: $($cpuInfo.LogicalProcessors)" -ForegroundColor Gray
Write-Host "  Frecuencia máxima:   $($cpuInfo.MaxClockSpeed) MHz" -ForegroundColor Gray

Write-Host "`nRAM:" -ForegroundColor White
Write-Host "  Total:       $($ramInfo.TotalMB) MB ($([math]::Round($ramInfo.TotalMB / 1024, 1)) GB)" -ForegroundColor Gray
Write-Host "  Disponible:  $($ramInfo.FreeMB) MB" -ForegroundColor Gray
Write-Host "  En uso:      $($ramInfo.UsedMB) MB ($([math]::Round(($ramInfo.UsedMB / $ramInfo.TotalMB) * 100, 1))%)" -ForegroundColor Gray

Write-Host "`nDisco ($($env:SystemDrive)):" -ForegroundColor White
Write-Host "  Tipo:        $($diskInfo.Type)" -ForegroundColor Gray
Write-Host "  Total:       $($diskInfo.TotalGB) GB" -ForegroundColor Gray
Write-Host "  Disponible:  $($diskInfo.FreeGB) GB" -ForegroundColor Gray

# ============================================
# DETERMINAR PERFIL ÓPTIMO
# ============================================

$detectedProfile = $null

# 1. Si se fuerza un perfil
if ($ForceProfile) {
    if ($config.profiles.PSObject.Properties.Name -contains $ForceProfile) {
        $detectedProfile = $ForceProfile
        $detectionMethod = "Manual (forzado)"
    } else {
        Write-Host "`n❌ Perfil '$ForceProfile' no existe" -ForegroundColor Red
        Write-Host "Perfiles válidos: $($config.profiles.PSObject.Properties.Name -join ', ')" -ForegroundColor Yellow
        exit 1
    }
}
# 2. Detectar por nombre de CPU
elseif (-not $detectedProfile) {
    $profileFromCPU = Detect-ProfileFromCPUName -cpuName $cpuInfo.Name -profiles $config
    if ($profileFromCPU) {
        $detectedProfile = $profileFromCPU
        $detectionMethod = "Familia de procesador"
    }
}
# 3. Detectar por especificaciones
if (-not $detectedProfile) {
    $detectedProfile = Detect-ProfileFromSpecs -cores $cpuInfo.Cores -ramMB $ramInfo.TotalMB -profiles $config
    $detectionMethod = "Especificaciones (cores + RAM)"
}

$profile = $config.profiles.$detectedProfile

Write-Host "`n=== Perfil Detectado ===" -ForegroundColor Cyan
Write-Host "Perfil:       " -NoNewline -ForegroundColor White
Write-Host "$detectedProfile" -ForegroundColor Green
Write-Host "Nombre:       $($profile.name)" -ForegroundColor White
Write-Host "Método:       $detectionMethod" -ForegroundColor Gray
Write-Host "Descripción:  $($profile.description)" -ForegroundColor Gray

# ============================================
# MOSTRAR CONFIGURACIÓN RECOMENDADA
# ============================================

Write-Host "`n=== Configuración Recomendada ===" -ForegroundColor Cyan

Write-Host "`nUmbrales de rendimiento:" -ForegroundColor White
Write-Host "  CPU máxima permitida:         $($profile.thresholds.cpu_max_percent)%" -ForegroundColor Gray
Write-Host "  RAM mínima disponible:        $($profile.thresholds.ram_min_available_mb) MB" -ForegroundColor Gray
Write-Host "  Requests/segundo esperados:   > $($profile.thresholds.http_min_requests_per_second) req/s" -ForegroundColor Gray
Write-Host "  Tiempo de respuesta máximo:   < $($profile.thresholds.http_max_response_time_ms) ms" -ForegroundColor Gray

Write-Host "`nConfiguración de tests Apache Bench:" -ForegroundColor White
Write-Host "  Peticiones:    $($profile.test_config.apache_bench.requests)" -ForegroundColor Gray
Write-Host "  Concurrencia:  $($profile.test_config.apache_bench.concurrency)" -ForegroundColor Gray

Write-Host "`nConfiguración de tests Playwright:" -ForegroundColor White
Write-Host "  Workers:       $($profile.test_config.playwright.workers)" -ForegroundColor Gray
Write-Host "  Video:         $($profile.test_config.playwright.video)" -ForegroundColor Gray
Write-Host "  Timeout:       $($profile.test_config.playwright.timeout_ms) ms" -ForegroundColor Gray

Write-Host "`nConfiguración de monitoreo:" -ForegroundColor White
Write-Host "  Intervalo:     $($profile.test_config.monitoring.interval_seconds) segundos" -ForegroundColor Gray
Write-Host "  Duración:      $($profile.test_config.monitoring.duration_seconds) segundos" -ForegroundColor Gray

# ============================================
# COMPARACIÓN CON OTROS PERFILES
# ============================================

Write-Host "`n=== Comparación con otros perfiles ===" -ForegroundColor Cyan

$allProfiles = @("minimal", "recommended", "optimal", "enterprise")
foreach ($p in $allProfiles) {
    $pData = $config.profiles.$p
    $isCurrent = $p -eq $detectedProfile
    
    $color = "Gray"
    $marker = "  "
    if ($isCurrent) {
        $color = "Green"
        $marker = "→ "
    }
    
    $cpuMatch = $cpuInfo.Cores -ge $pData.specs.cpu_cores
    $ramMatch = $ramInfo.TotalMB -ge $pData.specs.ram_mb
    
    $cpuIcon = if ($cpuMatch) { "OK" } else { "X" }
    $ramIcon = if ($ramMatch) { "OK" } else { "X" }
    
    Write-Host "$marker$($pData.name.PadRight(25)) " -NoNewline -ForegroundColor $color
    Write-Host "CPU: $cpuIcon $($pData.specs.cpu_cores) cores  " -NoNewline -ForegroundColor $color
    Write-Host "RAM: $ramIcon $([math]::Round($pData.specs.ram_mb / 1024, 0)) GB  " -NoNewline -ForegroundColor $color
    Write-Host "Disco: $($pData.specs.disk_type)" -ForegroundColor $color
}

# ============================================
# APLICAR CONFIGURACIÓN (si no es solo vista)
# ============================================

if (-Not $ShowOnly) {
    Write-Host "`n=== Aplicando configuración ===" -ForegroundColor Cyan
    
    # Crear archivo de perfil activo
    $activeProfilePath = ".hardware-profile"
    @{
        profile = $detectedProfile
        detected_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
        cpu_cores = $cpuInfo.Cores
        ram_mb = $ramInfo.TotalMB
        disk_type = $diskInfo.Type
        config = $profile
    } | ConvertTo-Json -Depth 10 | Out-File $activeProfilePath
    
    Write-Host "✅ Perfil guardado en: $activeProfilePath" -ForegroundColor Green
    Write-Host "   Los scripts de testing usarán esta configuración automáticamente" -ForegroundColor White
    
    # Actualizar playwright.config.ts si existe
    $playwrightConfig = "playwright.config.ts"
    if (Test-Path $playwrightConfig) {
        $configContent = Get-Content $playwrightConfig -Raw
        
        # Actualizar workers
        if ($configContent -match "workers:\s*\d+") {
            $configContent = $configContent -replace "workers:\s*\d+", "workers: $($profile.test_config.playwright.workers)"
            Write-Host "✅ playwright.config.ts actualizado (workers: $($profile.test_config.playwright.workers))" -ForegroundColor Green
        }
        
        # Actualizar timeout
        if ($configContent -match "timeout:\s*\d+") {
            $configContent = $configContent -replace "timeout:\s*\d+", "timeout: $($profile.test_config.playwright.timeout_ms)"
        }
        
        $configContent | Out-File $playwrightConfig -Encoding UTF8 -NoNewline
    }
    
    Write-Host "`n✅ Configuración aplicada exitosamente" -ForegroundColor Green
} else {
    Write-Host "`n💡 Modo solo-vista activado. Para aplicar la configuración ejecuta sin -ShowOnly" -ForegroundColor Yellow
}

# ============================================
# SIGUIENTES PASOS
# ============================================

Write-Host "`n=== Siguientes pasos ===" -ForegroundColor Cyan
Write-Host "Para ejecutar tests con esta configuración:" -ForegroundColor White
Write-Host "  .\scripts\full-hardware-test.ps1" -ForegroundColor Gray
Write-Host "`nPara cambiar el perfil manualmente:" -ForegroundColor White
Write-Host "  .\scripts\detect-hardware-profile.ps1 -ForceProfile optimal" -ForegroundColor Gray
Write-Host "`nPerfiles disponibles: minimal, recommended, optimal, enterprise" -ForegroundColor Gray
