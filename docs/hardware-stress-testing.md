# Guía de Stress Testing de Hardware para Lunnys Perú

Esta guía documenta cómo realizar pruebas de carga de hardware sin virtualización para validar que el sistema cumple los requisitos mínimos operables.

## 🎯 Objetivo

Verificar que el hardware disponible puede ejecutar:
- XAMPP (Apache + MariaDB)
- Laravel con PHP 8.2
- Node.js + Vite
- Playwright (tests E2E)
- Generación de PDFs con dompdf
- Carga concurrente de usuarios

---

## 📊 Herramientas de Benchmarking

### 1. **Apache Bench (ab)** - Viene con XAMPP
Simula múltiples usuarios concurrentes golpeando el servidor.

```powershell
# Test básico: 100 peticiones, 10 concurrentes
C:\xampp\apache\bin\ab.exe -n 100 -c 10 http://127.0.0.1:8000/login

# Test intenso: 1000 peticiones, 50 concurrentes
C:\xampp\apache\bin\ab.exe -n 1000 -c 50 -t 60 http://127.0.0.1:8000/dashboard

# Con autenticación (después de login)
C:\xampp\apache\bin\ab.exe -n 500 -c 20 -C "laravel_session=TU_SESSION_COOKIE" http://127.0.0.1:8000/clientes
```

**Métricas clave:**
- `Requests per second`: Debe ser > 50 req/s en hardware mínimo
- `Time per request (mean)`: < 200ms para operaciones simples
- `Failed requests`: Debe ser 0


### 2. **K6 (Grafana)** - Load Testing Moderno
Herramienta profesional de carga, escrita en Go.

#### Instalación:
```powershell
# Con Chocolatey
choco install k6

# O descarga directa desde: https://k6.io/docs/get-started/installation/
```

#### Script de prueba para Laravel:
```javascript
// k6-load-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 10 },  // Escala a 10 usuarios en 30s
    { duration: '1m', target: 50 },   // Escala a 50 usuarios en 1min
    { duration: '30s', target: 0 },   // Baja a 0 usuarios en 30s
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% de peticiones < 500ms
    http_req_failed: ['rate<0.01'],   // < 1% de errores
  },
};

export default function () {
  // Test de login
  const loginRes = http.post('http://127.0.0.1:8000/login', {
    usuario_o_correo: 'administrador',
    password: 'tu_password',
  });
  
  check(loginRes, {
    'login status 200': (r) => r.status === 200 || r.status === 302,
  });

  sleep(1);

  // Test de dashboard
  const dashboardRes = http.get('http://127.0.0.1:8000/dashboard');
  check(dashboardRes, {
    'dashboard status 200': (r) => r.status === 200,
    'dashboard carga < 1s': (r) => r.timings.duration < 1000,
  });

  sleep(2);
}
```

#### Ejecución:
```powershell
# Test normal
k6 run k6-load-test.js

# Test con reporte HTML
k6 run --out json=resultados.json k6-load-test.js
```


### 3. **Playwright Load Testing** - Ya lo tienes instalado
Usa Playwright para simular usuarios reales navegando.

```typescript
// e2e/stress/concurrent-users.spec.ts
import { test } from '@playwright/test';

test.describe('Prueba de carga con usuarios concurrentes', () => {
  // Simula 10 usuarios registrando clientes simultáneamente
  for (let i = 0; i < 10; i++) {
    test(`Usuario ${i + 1} registra cliente`, async ({ browser }) => {
      const context = await browser.newContext();
      const page = await context.newPage();
      
      // Login
      await page.goto('/login');
      await page.fill('[name="usuario_o_correo"]', 'administrador');
      await page.fill('[name="password"]', 'tu_password');
      await page.click('button[type="submit"]');
      
      // Registrar cliente
      await page.goto('/clientes/registrar');
      await page.fill('[name="tipo_documento"]', 'DNI');
      await page.fill('[name="numero_documento"]', `1234567${i}`);
      await page.fill('[name="nombres"]', `Cliente Test ${i}`);
      await page.fill('[name="apellidos"]', 'Prueba Carga');
      await page.fill('[name="correo"]', `test${i}@example.com`);
      await page.fill('[name="telefono"]', '999888777');
      await page.fill('[name="direccion"]', 'Av Test 123');
      await page.click('button[type="submit"]');
      
      await context.close();
    });
  }
});
```

#### Ejecutar con múltiples workers:
```powershell
# Cambia temporalmente en playwright.config.ts: workers: 5
npx playwright test e2e/stress/ --workers=5
```


### 4. **Locust** - Python Load Testing
Framework en Python para pruebas de carga con UI web.

#### Instalación:
```powershell
pip install locust
```

#### Script de prueba:
```python
# locustfile.py
from locust import HttpUser, task, between

class LunnysUser(HttpUser):
    wait_time = between(1, 3)
    
    def on_start(self):
        """Login al iniciar"""
        self.client.post("/login", {
            "usuario_o_correo": "administrador",
            "password": "tu_password"
        })
    
    @task(3)  # Peso 3: se ejecuta 3x más que otras
    def view_dashboard(self):
        self.client.get("/dashboard")
    
    @task(2)
    def view_clients(self):
        self.client.get("/clientes")
    
    @task(1)
    def view_contracts(self):
        self.client.get("/contratos")
    
    @task(1)
    def create_client(self):
        self.client.post("/clientes", {
            "tipo_documento": "DNI",
            "numero_documento": "12345678",
            "nombres": "Test",
            "apellidos": "Usuario",
            "correo": "test@example.com",
            "telefono": "999888777",
            "direccion": "Av Test 123"
        })
```

#### Ejecución:
```powershell
# Inicia servidor web en http://localhost:8089
locust -f locustfile.py --host=http://127.0.0.1:8000

# O sin UI (headless):
locust -f locustfile.py --headless --users 50 --spawn-rate 5 --run-time 2m --host=http://127.0.0.1:8000
```

**En la UI puedes configurar:**
- Número de usuarios simulados
- Tasa de spawn (cuántos por segundo)
- Host a atacar


### 5. **Siege** - HTTP Load Tester
Similar a ab pero más potente.

#### Instalación (Windows):
```powershell
# Descargar desde: https://github.com/JoeDog/siege
# O usar WSL: sudo apt install siege
```

#### Uso:
```bash
# Test básico
siege -c 25 -t 1M http://127.0.0.1:8000/dashboard

# Con archivo de URLs
siege -c 50 -t 2M -f urls.txt

# Benchmark mode (sin delays)
siege -c 100 -r 10 -b http://127.0.0.1:8000/login
```

Archivo `urls.txt`:
```
http://127.0.0.1:8000/dashboard
http://127.0.0.1:8000/clientes
http://127.0.0.1:8000/contratos
http://127.0.0.1:8000/pagos
```


### 6. **Laravel Dusk** - Browser Testing Nativo
Ya tienes Playwright, pero Dusk es alternativa nativa de Laravel.

```powershell
composer require --dev laravel/dusk
php artisan dusk:install
php artisan dusk
```

---

## 🖥️ Monitoreo de Hardware Durante Tests

### 1. **Performance Monitor (Windows nativo)**
```powershell
# Abrir monitor de rendimiento
perfmon
```

**Contadores críticos a vigilar:**
- `Processor % Processor Time`: Debe estar < 80%
- `Memory % Committed Bytes In Use`: Debe estar < 90%
- `PhysicalDisk % Disk Time`: Debe estar < 70%
- `Network Interface Bytes Total/sec`: Para diagnosticar cuellos de botella


### 2. **Task Manager Avanzado**
```powershell
# Abrir con Ctrl+Shift+Esc
# Ir a pestaña "Performance" y monitorear:
# - CPU (por núcleo)
# - RAM (uso/disponible)
# - Disco (lectura/escritura)
# - Red (throughput)
```


### 3. **PowerShell Script de Monitoreo**
Script automatizado que registra métricas durante las pruebas:

```powershell
# scripts/monitor-hardware.ps1
param(
    [int]$DurationSeconds = 60,
    [int]$IntervalSeconds = 5
)

$outputFile = "hardware-metrics-$(Get-Date -Format 'yyyyMMdd-HHmmss').csv"
$iterations = [math]::Ceiling($DurationSeconds / $IntervalSeconds)

"Timestamp,CPU_%,RAM_MB,Disk_Read_MB,Disk_Write_MB,Network_Mbps" | Out-File $outputFile

Write-Host "Monitoreando hardware por $DurationSeconds segundos..." -ForegroundColor Cyan

for ($i = 0; $i -lt $iterations; $i++) {
    $cpu = (Get-Counter '\Processor(_Total)\% Processor Time').CounterSamples.CookedValue
    $ram = (Get-Counter '\Memory\Available MBytes').CounterSamples.CookedValue
    $diskRead = (Get-Counter '\PhysicalDisk(_Total)\Disk Read Bytes/sec').CounterSamples.CookedValue / 1MB
    $diskWrite = (Get-Counter '\PhysicalDisk(_Total)\Disk Write Bytes/sec').CounterSamples.CookedValue / 1MB
    $network = (Get-Counter '\Network Interface(*)\Bytes Total/sec').CounterSamples.CookedValue / 1MB * 8
    
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    "$timestamp,$([math]::Round($cpu,2)),$([math]::Round($ram,2)),$([math]::Round($diskRead,2)),$([math]::Round($diskWrite,2)),$([math]::Round($network,2))" | Out-File $outputFile -Append
    
    Write-Host "[$timestamp] CPU: $([math]::Round($cpu,1))% | RAM: $([math]::Round($ram,0)) MB disponible" -ForegroundColor Yellow
    
    Start-Sleep -Seconds $IntervalSeconds
}

Write-Host "`n✅ Métricas guardadas en: $outputFile" -ForegroundColor Green
Write-Host "Analiza el archivo CSV con Excel o Python/pandas" -ForegroundColor White
```

**Uso:**
```powershell
# Monitorear por 2 minutos
.\scripts\monitor-hardware.ps1 -DurationSeconds 120 -IntervalSeconds 5

# En otra terminal, ejecuta tus tests
npm run e2e:cycles
```


### 4. **Resource Monitor (resmon)**
```powershell
# Herramienta nativa de Windows más detallada que Task Manager
resmon
```

---

## 🧪 Suite de Tests Completa

Script que combina todo:

```powershell
# scripts/full-hardware-test.ps1

Write-Host "=== PRUEBA DE HARDWARE COMPLETA PARA LUNNYS PERÚ ===" -ForegroundColor Cyan

# 1. Verificar que el servidor esté corriendo
Write-Host "`n[1/5] Verificando servidor Laravel..." -ForegroundColor Yellow
$response = Invoke-WebRequest -Uri "http://127.0.0.1:8000" -UseBasicParsing -ErrorAction SilentlyContinue
if (-not $response) {
    Write-Host "❌ Servidor no responde. Ejecuta: php artisan serve" -ForegroundColor Red
    exit 1
}
Write-Host "✅ Servidor activo" -ForegroundColor Green

# 2. Test de carga con Apache Bench
Write-Host "`n[2/5] Ejecutando Apache Bench (100 req, 10 concurrent)..." -ForegroundColor Yellow
C:\xampp\apache\bin\ab.exe -n 100 -c 10 http://127.0.0.1:8000/login

# 3. Iniciar monitoreo de hardware en segundo plano
Write-Host "`n[3/5] Iniciando monitoreo de hardware..." -ForegroundColor Yellow
$monitorJob = Start-Job -FilePath ".\scripts\monitor-hardware.ps1" -ArgumentList 180, 5
Write-Host "✅ Monitoreo iniciado (Job ID: $($monitorJob.Id))" -ForegroundColor Green

# 4. Ejecutar tests E2E intensivos
Write-Host "`n[4/5] Ejecutando tests E2E con Playwright..." -ForegroundColor Yellow
npm run e2e:cycles

# 5. Esperar y recoger datos del monitoreo
Write-Host "`n[5/5] Finalizando monitoreo..." -ForegroundColor Yellow
Wait-Job -Job $monitorJob
$monitorOutput = Receive-Job -Job $monitorJob
Remove-Job -Job $monitorJob

Write-Host "`n=== PRUEBA COMPLETADA ===" -ForegroundColor Cyan
Write-Host $monitorOutput
```

---

## 📈 Interpretación de Resultados

### Apache Bench (ab)
```
Concurrency Level:      10
Time taken for tests:   5.234 seconds
Complete requests:      100
Failed requests:        0
Requests per second:    19.11 [#/sec]  ⬅️ Debe ser > 50 en hardware adecuado
Time per request:       523.402 [ms]   ⬅️ Debe ser < 300ms
```

### K6
```
✓ http_req_duration..............: avg=250ms  p(95)=450ms  ⬅️ Si p95 < 500ms → OK
✗ http_req_failed................: 5.00%                    ⬅️ Si > 1% → Problema
```

### Playwright
```
117 passed (3.5m)  ⬅️ Si termina sin timeouts → OK
Videos: 117 (2.3 GB) ⬅️ Monitorear espacio en disco
```

### Monitoreo Hardware
```
CPU > 90% constante      → Necesitas más núcleos
RAM > 85% constante      → Necesitas más memoria
Disk > 80% constante     → Cambiar a SSD
Network > 80% throughput → Problema de red (raro en local)
```

---

## ✅ Checklist de Validación

Hardware es **suficiente** si:
- [ ] Apache Bench: > 50 req/s, < 1% fallos
- [ ] K6: p95 latency < 500ms
- [ ] Playwright: Completa 117 tests sin timeouts
- [ ] CPU promedio < 80% durante tests
- [ ] RAM disponible > 1 GB durante tests
- [ ] Disco no satura (< 70% utilización)

Hardware **necesita upgrade** si:
- [ ] Fallos constantes en tests de carga
- [ ] Timeouts en Playwright
- [ ] CPU > 95% por más de 30 segundos
- [ ] RAM < 500 MB disponible (swap activo)
- [ ] Tests toman > 2x el tiempo esperado

---

## 🚀 Recomendaciones Finales

1. **Empieza simple**: Apache Bench (ya lo tienes con XAMPP)
2. **Luego escala**: K6 o Locust para tests realistas
3. **Monitorea siempre**: Usa `monitor-hardware.ps1` en paralelo
4. **Documenta**: Guarda métricas de cada test para comparar

**Orden sugerido de ejecución:**
```powershell
# Terminal 1: Servidor Laravel
php artisan serve

# Terminal 2: Monitoreo
.\scripts\monitor-hardware.ps1 -DurationSeconds 300

# Terminal 3: Tests de carga
C:\xampp\apache\bin\ab.exe -n 1000 -c 50 http://127.0.0.1:8000/dashboard

# Terminal 4: Tests E2E
npm run e2e
```

---

**Autor:** Equipo Lunnys Perú  
**Fecha:** 2026-10-16  
**Versión:** 1.0
