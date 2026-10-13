# 💥 SUITE DE PRUEBAS EXHAUSTIVAS - SISTEMA CAPSTONE

**Objetivo:** Poner a prueba los módulos críticos del sistema con casos extremos, datos sucios, concurrencia y situaciones inesperadas para garantizar robustez y confiabilidad.

---

## 🎯 Filosofía de Testing

> **"Si no rompe en los tests, romperá en producción"**

Esta suite de pruebas NO busca solo validar casos felices. Busca **DESTRUIR** la aplicación con:
- 💀 **Casos extremos:** Datos en límites, valores absurdos
- 🔥 **Ataques maliciosos:** SQL injection, XSS, mass assignment
- ⚡ **Concurrencia:** Race conditions, locks, transacciones
- 🧨 **Datos sucios:** Strings largos, caracteres especiales, nulls
- 🎭 **Estados inconsistentes:** Flujos rotos, validaciones faltantes

---

## 📁 Estructura de Carpetas

```
testsphpunit/
│
├── Contratos/              ← PRIORIDAD ALTA (100% implementado)
│   ├── PLAN_DE_PRUEBAS.md
│   └── ContractTest.php (a crear)
│
├── Pagos/                  ← PRIORIDAD ALTA (78% implementado, 7 métodos faltantes)
│   ├── PLAN_DE_PRUEBAS.md
│   └── PaymentTest.php (a crear)
│
├── Almacen/                ← PRIORIDAD ALTA (97% implementado, 1 método crítico faltante)
│   ├── PLAN_DE_PRUEBAS.md
│   ├── EquipmentTest.php (a crear)
│   ├── MaterialTest.php (a crear)
│   └── KardexTest.php (a crear)
│
├── Instalaciones/          ← PRIORIDAD MEDIA (100% implementado)
│   ├── PLAN_DE_PRUEBAS.md
│   ├── InstallationOrderTest.php (a crear)
│   └── InstallationExecutionTest.php (a crear)
│
└── README.md               ← Este archivo
```

---

## 📊 Estado General del Sistema

| Módulo | Métodos Impl. | Métodos Faltantes | Cobertura | Prioridad | Tests Planeados |
|--------|--------------|-------------------|-----------|-----------|----------------|
| **Contratos** | ✅ 19/19 | ❌ 0 | 100% | 🔴 ALTA | 80+ casos |
| **Pagos** | ✅ 31/33 | ❌ 2 | 93.9% | 🔴 ALTA | 100+ casos |
| **Almacén (Equipos)** | ✅ 18/18 | ❌ 0 | 100% | 🔴 ALTA | 60+ casos |
| **Almacén (Materiales)** | ✅ 11/11 | ❌ 0 | 100% | 🔴 ALTA | 40+ casos |
| **Almacén (Kardex)** | ✅ 12/12 | ❌ 0 | 100% | 🔴 ALTA | 50+ casos |
| **Instalaciones** | ✅ 27/27 | ❌ 0 | 100% | 🟡 MEDIA | 70+ casos |
| **TOTAL** | ✅ 118/120 | ❌ 2 | **98.3%** | - | **400+ casos** |

### 🚨 Métodos Faltantes:

#### **PAGOS (2 faltantes - BAJA prioridad):**
1. ❌ `createReminder` (RF-PAG-29) - 🟢 BAJA - Crear recordatorios internos (los emails ya existen)
2. ❌ `manageReactivation` (RF-PAG-32) - 🟡 MEDIA - Gestión específica reactivación post-regularización

#### **EQUIPOS:**
✅ **preventDuplicateAssignment YA ESTÁ IMPLEMENTADO** con `lockForUpdate()` en `InstallationOrderService::reserveEquipment()`

---

## 🚀 Ejecución de Tests

### **Ejecutar por Módulo:**

```bash
# 1. CONTRATOS
php artisan test testsphpunit/Contratos/ContractTest.php

# 2. PAGOS
php artisan test testsphpunit/Pagos/PaymentTest.php

# 3. ALMACÉN - EQUIPOS
php artisan test testsphpunit/Almacen/EquipmentTest.php

# 4. ALMACÉN - MATERIALES
php artisan test testsphpunit/Almacen/MaterialTest.php

# 5. ALMACÉN - KARDEX
php artisan test testsphpunit/Almacen/KardexTest.php

# 6. INSTALACIONES - PEDIDOS
php artisan test testsphpunit/Instalaciones/InstallationOrderTest.php

# 7. INSTALACIONES - EJECUCIÓN
php artisan test testsphpunit/Instalaciones/InstallationExecutionTest.php
```

### **Ejecutar por Prioridad:**

```bash
# ALTA PRIORIDAD (Contratos + Pagos + Almacén)
php artisan test testsphpunit/Contratos/ testsphpunit/Pagos/ testsphpunit/Almacen/

# MEDIA PRIORIDAD (Instalaciones)
php artisan test testsphpunit/Instalaciones/

# TODOS LOS TESTS
php artisan test testsphpunit/
```

### **Ejecutar Test Específico:**

```bash
# Por nombre de test
php artisan test --filter=test_store_con_datos_validos

# Por método
php artisan test --filter=store

# Por clase
php artisan test testsphpunit/Contratos/ContractTest.php
```

### **Con Cobertura de Código:**

```bash
# Requiere Xdebug instalado
php artisan test testsphpunit/ --coverage

# HTML report
php artisan test testsphpunit/ --coverage-html coverage/
```

---

## 🧪 Categorías de Tests

### **1. Tests de Validación (40%)**
- Campos requeridos
- Tipos de datos
- Límites de longitud
- Enums/estados válidos
- Foreign keys

### **2. Tests de Seguridad (20%)**
- SQL Injection
- XSS (Cross-Site Scripting)
- Mass Assignment
- Path Traversal
- CSRF protection

### **3. Tests de Lógica de Negocio (25%)**
- Estados de contratos
- Cálculo de fechas
- Generación de pagos
- Flujo de instalaciones
- Descuento de stock

### **4. Tests de Concurrencia (10%)**
- Race conditions
- Double submissions
- Locks de base de datos
- Transacciones

### **5. Tests de Edge Cases (5%)**
- Valores límite
- Strings muy largos
- Fechas extremas
- Stock en 0
- Divisiones por 0

---

## 🎭 Ejemplos de Casos "Destructivos"

### **SQL Injection:**
```php
$this->post('/contracts', [
    'direccion_instalacion' => "'; DROP TABLE contracts;--"
]);
// ✅ Debe escapar y guardar literalmente
```

### **Race Condition:**
```php
// 2 usuarios confirman el mismo pago simultáneamente
$payment = Payment::factory()->create(['estado' => 'PENDIENTE']);

// Thread 1:
$this->post("/payments/{$payment->id}/confirm", [...]);

// Thread 2 (al mismo tiempo):
$this->post("/payments/{$payment->id}/confirm", [...]);

// ✅ Solo 1 debe tener éxito
```

### **XSS Attack:**
```php
$this->post('/contracts', [
    'referencia' => '<script>alert("XSS")</script>'
]);
// ✅ Debe sanitizar y no ejecutar el script
```

### **Stock Negativo:**
```php
$material = Material::factory()->create(['stock_actual' => 10]);

// 2 salidas simultáneas de 8 unidades cada una
// Thread 1: storeExit(8)
// Thread 2: storeExit(8)

// ✅ Solo 1 debe tener éxito (no stock negativo)
```

### **Doble Asignación de Equipo:**
```php
$equipment = Equipment::factory()->create(['estado' => 'DISPONIBLE']);

// Thread 1: assign(equipment, installation1)
// Thread 2: assign(equipment, installation2)

// ✅ Solo 1 debe tener éxito
```

---

## 📋 Checklist por Método

Para cada método crítico, verificar:

- [ ] **Happy Path** - Caso válido funciona
- [ ] **Validaciones** - Rechaza datos inválidos
- [ ] **SQL Injection** - Escapa caracteres peligrosos
- [ ] **XSS** - Sanitiza HTML/JS
- [ ] **Mass Assignment** - Ignora campos protegidos
- [ ] **Permisos** - Rechaza usuarios sin autorización
- [ ] **Estados** - Valida transiciones de estado
- [ ] **Integridad** - Verifica foreign keys
- [ ] **Concurrencia** - Maneja race conditions
- [ ] **Edge Cases** - Funciona en límites

---

## 🛠️ Herramientas y Configuración

### **Requisitos:**
- PHP 8.1+
- Laravel 10.x
- PHPUnit 10.x
- MySQL/PostgreSQL
- Faker para datos de prueba

### **Base de Datos de Testing:**
```bash
# .env.testing
DB_CONNECTION=mysql
DB_DATABASE=capstone_testing
APP_ENV=testing
```

### **Factories:**
Usar factories para generar datos de prueba:
```php
Contract::factory()->create([...]);
Payment::factory()->count(12)->create([...]);
```

### **Transacciones:**
Cada test se ejecuta en una transacción que se revierte:
```php
use RefreshDatabase; // En cada TestCase
```

---

## 📈 Objetivos de Cobertura

| Métrica | Objetivo | Actual |
|---------|----------|--------|
| **Líneas de Código** | 90%+ | - |
| **Métodos Críticos** | 100% | - |
| **Branches** | 85%+ | - |
| **Edge Cases** | 100% | - |

---

## 🚦 Semáforo de Prioridad

### 🔴 **CRÍTICO** (Debe pasar al 100%)
- Generación de pagos
- Confirmación de pagos
- Asignación de equipos (sin duplicados)
- Completar instalación (activa contrato + genera pagos)
- Descuento de stock en Kardex

### 🟡 **ALTA** (Debe pasar al 95%)
- Activación/reactivación de contratos
- Cancelación por impago
- Validaciones de estado
- Permisos de usuario
- Seguridad (SQL, XSS)

### 🟢 **MEDIA** (Debe pasar al 85%)
- Edge cases de fechas
- Performance con datos masivos
- Notificaciones por email
- Reportes y exports

---

## 📚 Recursos

### **Documentación:**
- [PLAN_DE_PRUEBAS.md](./Contratos/PLAN_DE_PRUEBAS.md) en cada carpeta
- [Mapeo_Metodos_Requerimientos.md](../Mapeo_Metodos_Requerimientos.md) - Trazabilidad completa

### **Comandos Útiles:**
```bash
# Ver lista de tests
php artisan test --list-tests

# Tests paralelos (más rápido)
php artisan test --parallel

# Solo tests que fallaron
php artisan test --failed

# Detener en primer fallo
php artisan test --stop-on-failure

# Modo verbose
php artisan test -v
```

---

## 🎯 Próximos Pasos

1. **Implementar métodos faltantes** (8 métodos, ver arriba)
2. **Crear archivos de tests** (ContractTest.php, PaymentTest.php, etc.)
3. **Ejecutar y corregir** hasta 100% de éxito
4. **Medir cobertura** con Xdebug
5. **Documentar resultados** en reporte final

---

## 👨‍💻 Autor

**Equipo Capstone**  
📅 Fecha: Octubre 2026  
🎓 Proyecto: Sistema de Gestión ISP

---

## 📄 Licencia

Este proyecto es parte del Capstone académico.

---

**¿Listo para hacer llorar al código? Let's break it! 💥**
