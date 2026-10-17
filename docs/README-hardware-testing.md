# 🖥️ Sistema de Detección Automática de Hardware

Este sistema detecta automáticamente las capacidades de tu hardware y ajusta los tests para obtener resultados óptimos.

## 🎯 ¿Cómo funciona?

1. **Detecta tu hardware**: CPU (modelo, núcleos), RAM (total, disponible), Disco (tipo: HDD/SSD/NVMe)
2. **Identifica el perfil óptimo**: Minimal → Recommended → Optimal → Enterprise
3. **Aplica configuración automáticamente**: Ajusta workers, concurrencia, timeouts, etc.

## 🚀 Uso Rápido

### Opción 1: Detección + Tests (Recomendado)
```powershell
# Detecta hardware y ejecuta suite completa
.\scripts\full-hardware-test.ps1
```

### Opción 2: Solo Detección
```powershell
# Solo ver qué hardware tienes
.\scripts\detect-hardware-profile.ps1 -ShowOnly

# Detectar y aplicar configuración
.\scripts\detect-hardware-profile.ps1
```

### Opción 3: Forzar un Perfil Específico
```powershell
# Si tu i7 no se detecta bien, forzarlo:
.\scripts\detect-hardware-profile.ps1 -ForceProfile optimal

# Luego ejecutar tests con ese perfil
.\scripts\full-hardware-test.ps1
```

## 📊 Perfiles Disponibles

| Perfil | CPU | RAM | Disco | Ejemplo de Hardware |
|--------|-----|-----|-------|---------------------|
| **minimal** | 2 cores | 4 GB | HDD | i3, Ryzen 3, Pentium |
| **recommended** | 4 cores | 8 GB | SSD | i5, Ryzen 5 |
| **optimal** | 8 cores | 16 GB | NVMe | **i7, Ryzen 7** ← Tu caso |
| **enterprise** | 16+ cores | 32 GB | NVMe RAID | i9, Xeon, Threadripper |

### ¿Qué cambia en cada perfil?

**Perfil OPTIMAL (i7):**
- Apache Bench: 2000 peticiones, 50 concurrentes (vs 100/5 en minimal)
- Playwright: 4 workers (vs 1 en minimal)
- Timeouts más cortos (30s vs 60s)
- Monitoreo más frecuente (cada 3s vs 10s)
- Umbrales más estrictos (CPU<80% vs CPU<95%)

## 🔧 Configuración Manual

Si quieres ajustar los perfiles, edita:
```
config/hardware-profiles.json
```

Ejemplo: Cambiar requests de Apache Bench para perfil optimal:
```json
{
  "profiles": {
    "optimal": {
      "test_config": {
        "apache_bench": {
          "requests": 3000,  // ← Cambia aquí
          "concurrency": 60
        }
      }
    }
  }
}
```

## 📁 Archivos Generados

Después de ejecutar la detección:
- `.hardware-profile` → Perfil activo (JSON con tu configuración)
- `playwright.config.ts` → Actualizado automáticamente con workers óptimos
- `hardware-metrics-*.csv` → Métricas de rendimiento

## 🧪 Ejemplos de Uso

### Caso 1: Primera vez en tu i7
```powershell
# 1. Detectar hardware
PS> .\scripts\detect-hardware-profile.ps1

=== Hardware Detectado ===
CPU: Intel Core i7-10700K @ 3.8GHz
Núcleos físicos: 8
RAM Total: 16384 MB (16 GB)
Disco: NVMe

=== Perfil Detectado ===
Perfil: optimal
Nombre: Óptimo (i7+)
✅ Perfil guardado en: .hardware-profile

# 2. Ejecutar tests con esa configuración
PS> .\scripts\full-hardware-test.ps1

📊 Perfil activo: Óptimo (i7+)
  ⚙️  Apache Bench: 2000 peticiones, 50 concurrentes
  ⚙️  Playwright: 4 workers
[... ejecuta tests optimizados para i7 ...]
```

### Caso 2: Forzar perfil enterprise en tu i7
```powershell
# Si quieres probar con configuración más agresiva
PS> .\scripts\detect-hardware-profile.ps1 -ForceProfile enterprise

=== Perfil Detectado ===
Perfil: enterprise
Método: Manual (forzado)
✅ Configuración aplicada

# Ahora los tests usarán 8 workers, 5000 requests, etc.
PS> .\scripts\full-hardware-test.ps1
```

### Caso 3: Re-detectar después de upgrade de RAM
```powershell
# Si agregaste más RAM, re-detecta
PS> Remove-Item .hardware-profile
PS> .\scripts\detect-hardware-profile.ps1

# Automáticamente detectará el nuevo perfil si corresponde
```

## ❓ Preguntas Frecuentes

### ¿Por qué mi i7 se detecta como "recommended" y no "optimal"?

Revisa la RAM. Necesitas al menos 12 GB para "optimal". Si tienes 8 GB, se queda en "recommended".

**Solución:** Forzar el perfil:
```powershell
.\scripts\detect-hardware-profile.ps1 -ForceProfile optimal
```

### ¿Puedo usar esto en un servidor remoto?

Sí. El script funciona en cualquier Windows con PowerShell. Detectará el hardware del servidor y aplicará el perfil correcto.

### ¿Se actualiza automáticamente el perfil?

Sí, cada 24 horas. Puedes forzar re-detección borrando `.hardware-profile`.

### ¿Los tests fallarán si mi hardware es "minimal"?

No. Los tests se ajustan para ser exitosos en cualquier hardware. Solo cambiarán los parámetros de carga (menos concurrencia, más timeout, etc.)

## 🎓 Comparación de Rendimiento

**Mismo test en diferentes perfiles:**

| Perfil | Apache Bench | Tiempo Tests E2E | RAM Usada |
|--------|--------------|------------------|-----------|
| minimal | 100 req @ 5 concurrent | ~8 minutos | ~3 GB |
| recommended | 500 req @ 20 concurrent | ~5 minutos | ~4 GB |
| **optimal (i7)** | 2000 req @ 50 concurrent | **~3 minutos** | **~6 GB** |
| enterprise | 5000 req @ 100 concurrent | ~2 minutos | ~8 GB |

**Tu i7 ejecutará los tests 2-3x más rápido que hardware básico.**

## 📖 Ver También

- `docs/hardware-stress-testing.md` - Guía completa de herramientas
- `config/hardware-profiles.json` - Configuración de perfiles
- `scripts/monitor-hardware.ps1` - Monitoreo manual
- `scripts/apache-bench-test.ps1` - Tests de carga HTTP

---

**TL;DR para tu i7:**
```powershell
.\scripts\detect-hardware-profile.ps1  # Detecta como "optimal"
.\scripts\full-hardware-test.ps1       # Ejecuta tests optimizados
```
