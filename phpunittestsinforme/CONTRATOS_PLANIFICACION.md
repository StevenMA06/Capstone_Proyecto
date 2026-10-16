# 📋 PLANIFICACIÓN DE TESTS - MÓDULO CONTRATOS

> **Antes de crear los tests**, este documento identifica los métodos disponibles, el código a probar y las dependencias necesarias.

---

## 📊 RESUMEN

**Controlador:** `app/Http/Controllers/ContractController.php`  
**Repository:** `app/Domain/Contracts/ContractRepository.php`  
**Request:** `app/Http/Requests/StoreContractRequest.php`  
**Modelo:** `app/Models/Contract.php`

**Métodos Críticos para Testear:**
- ✅ `store()` - Crear contrato (36 tests implementados)
- ⏳ `update()` - Actualizar contrato
- ⏳ `reactivate()` - Reactivar contrato cancelado
- ⏳ `requestCancellation()` - Solicitar cancelación
- ⏳ `approveCancellation()` - Aprobar cancelación

---

## 1️⃣ MÉTODO: `store()` - Crear Contrato

### 📍 Ubicación
**Archivo:** `app/Http/Controllers/ContractController.php` (línea 75)  
**Repository:** `app/Domain/Contracts/ContractRepository.php::create()`

### 💻 Código del Método

```php
public function store(StoreContractRequest $request)
{
    $data = $request->validated();
    if (! $request->user()->isManager()) {
        $data['responsable_id'] = (int) $request->user()->empleado_id;
    }
    $id = $this->contracts->create($data, (int) $request->user()->getKey());

    return redirect()->route('contracts.show', $id)
        ->with('status', 'Contrato registrado correctamente...');
}
```

### 🔧 Request Validation (StoreContractRequest)

```php
'cliente_id' => 'required|integer|exists:clientes,cliente_id',
'plan_id' => 'required|integer|exists:planes,plan_id',
'fecha_inicio' => 'required|date|after_or_equal:today',
'duracion_meses' => 'required|integer|min:1|max:65535',
'direccion_instalacion' => 'required|string|max:255',
'distrito' => 'required|string|max:100',
'provincia' => 'nullable|string|max:100',
'departamento' => 'nullable|string|max:100',
'responsable_id' => 'nullable|integer|exists:empleados,empleado_id',
'zona_id' => 'nullable|integer|exists:zonas_cobertura,zona_id',
'observaciones' => 'nullable|string|max:500'
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Estado requerido | Notas |
|------------|----------------|------------------|-------|
| Usuario autenticado | `User::factory()` | Activo, con rol | Necesita `empleado_id` |
| Empleado | `Employee::factory()` | ACTIVO, no cesado | `cargo` válido |
| Cliente | `Client::factory()` | ACTIVO | Debe tener `distrito` |
| Plan | `ServicePlan::factory()` | ACTIVO | `precio_mensual > 0` |
| Zona cobertura | `CoverageZone::factory()` | ACTIVA | `distrito` coincidente |
| Rol | `Role::where('nombre', 'Administrador')` | - | Asignar al usuario |

### ✅ Tests Implementados (36 casos)

**Archivo:** `testsphpunit/Contratos/ContractStoreTest.php`

#### Categorías de Tests:
1. **Clientes inválidos** (7 casos):
   - ID inexistente (99999)
   - ID cero (0)
   - ID negativo (-1)
   - Texto ('ABC')
   - NULL
   - ID muy grande (999999999)
   - Decimal (5.5)

2. **Duraciones inválidas** (10 casos):
   - Negativa (-5)
   - Cero (0)
   - Texto ('doce')
   - Decimal (12.5)
   - NULL
   - Muy grande (999)
   - Negativa grande (-100)
   - Float negativo (-5.5)
   - String vacío ('')
   - Infinito (PHP_INT_MAX)

3. **Fechas inválidas** (8 casos):
   - 30 de febrero ('2024-02-30')
   - 32 de enero ('2024-01-32')
   - Mes 13 ('2024-13-01')
   - Texto ('mañana')
   - Formato incorrecto ('13/10/2024')
   - Solo año ('2024')
   - NULL
   - Vacía ('')

4. **Ataques de seguridad** (10 casos):
   - SQL Injection: DROP TABLE
   - SQL Injection: DELETE ALL
   - SQL Injection: UNION SELECT
   - XSS: Script tag
   - XSS: IMG onerror
   - XSS: Iframe
   - HTML Injection
   - JavaScript URL
   - SQL Comment bypass
   - Path Traversal

5. **Caso válido** (1 caso):
   - Contrato con todos los datos correctos

**Resultado:** 35/35 ataques bloqueados (100%)

---

## 2️⃣ MÉTODO: `update()` - Actualizar Contrato

### 📍 Ubicación
**Archivo:** `app/Http/Controllers/ContractController.php` (línea 133)  
**Repository:** `app/Domain/Contracts/ContractRepository.php::update()`

### 💻 Código del Método

```php
public function update(StoreContractRequest $request, int $contract)
{
    $data = $request->validated();
    if (! $request->user()->isManager()) {
        $data['responsable_id'] = (int) $this->contracts->detail($contract)->responsable_id;
    }
    $feedback = $this->contracts->update($contract, $data, $request->user()->getKey());

    return redirect()->route('contracts.show', $contract)
        ->with('status', $feedback['message'])
        ->with('status_kind', $feedback['kind']);
}
```

### 🔧 Validaciones
Usa la misma validación que `store()` (StoreContractRequest)

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Estado requerido | Notas |
|------------|----------------|------------------|-------|
| Contrato existente | `Contract::factory()` | PENDIENTE o ACTIVO | No debe estar CANCELADO |
| Usuario autenticado | `User::factory()` | Activo, con rol | Permiso: `contracts.update` |
| Cliente | `Client::factory()` | ACTIVO | - |
| Plan | `ServicePlan::factory()` | ACTIVO | - |

### ⏳ Tests Propuestos (15 casos)

#### Casos de Validación:
1. Actualiza dirección de contrato PENDIENTE
2. Actualiza duración de contrato PENDIENTE
3. Rechaza actualizar contrato CANCELADO
4. Rechaza actualizar contrato ACTIVO sin permisos
5. Rechaza cliente_id inválido
6. Rechaza plan_id inválido
7. Rechaza duración negativa
8. Rechaza fecha_inicio pasada
9. SQL Injection en dirección
10. XSS en observaciones

#### Casos de Lógica de Negocio:
11. Mantiene responsable_id si usuario no es gerente
12. Permite cambiar responsable_id si usuario es gerente
13. Actualiza zona_id correctamente
14. Valida que nueva dirección tenga cobertura
15. Genera log de auditoría del cambio

**Prioridad:** 🟠 ALTA (método crítico)

---

## 3️⃣ MÉTODO: `reactivate()` - Reactivar Contrato

### 📍 Ubicación
**Archivo:** `app/Http/Controllers/ContractController.php` (línea 270)  
**Repository:** `app/Domain/Contracts/ContractRepository.php::reactivate()`

### 💻 Código del Método

```php
public function reactivate(ReactivateContractRequest $request, int $contract)
{
    try {
        $feedback = $this->contracts->reactivate(
            $contract,
            $request->validated(),
            $request->user()->getKey()
        );

        return redirect()->route('contracts.show', $contract)
            ->with('status', $feedback['message'])
            ->with('status_kind', $feedback['kind']);
    } catch (\Exception $e) {
        return back()->withErrors(['error' => $e->getMessage()]);
    }
}
```

### 🔧 Request Validation (ReactivateContractRequest)

```php
'fecha_inicio' => 'required|date|after_or_equal:today',
'duracion_meses' => 'required|integer|min:1|max:65535',
'direccion_instalacion' => 'required|string|max:255',
'observaciones_reactivacion' => 'nullable|string|max:500'
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Estado requerido | Notas |
|------------|----------------|------------------|-------|
| Contrato existente | `Contract::factory()` | **CANCELADO** | Obligatorio estar cancelado |
| Usuario autenticado | `User::factory()` | Activo | Permiso: `contracts.reactivate` |
| Cliente | `Client::factory()` | ACTIVO | Debe no tener deuda |
| Plan | `ServicePlan::factory()` | ACTIVO | - |

### ⏳ Tests Propuestos (12 casos)

#### Casos de Validación:
1. Reactiva contrato CANCELADO exitosamente
2. Rechaza reactivar contrato ACTIVO
3. Rechaza reactivar contrato PENDIENTE
4. Rechaza reactivar sin permiso
5. Rechaza fecha_inicio pasada
6. Rechaza duración negativa
7. Rechaza duración cero

#### Casos de Lógica de Negocio:
8. Genera nuevo código de contrato
9. Conserva historial de cancelación
10. Valida cliente sin deuda
11. Crea nuevo pedido de instalación
12. Genera log de reactivación

**Prioridad:** 🟠 ALTA (RF-CON-14, RF-CON-15)

---

## 4️⃣ MÉTODO: `requestCancellation()` - Solicitar Cancelación

### 📍 Ubicación
**Archivo:** `app/Http/Controllers/ContractController.php` (línea 146)

### 💻 Código del Método

```php
public function requestCancellation(Request $request, int $contract)
{
    abort_unless($request->user()->hasPermission('contracts.cancel.request'), 403);

    $request->validate([
        'motivo' => 'required|string|max:500',
        'evidencia' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'
    ]);

    // Lógica de solicitud...
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Estado requerido | Notas |
|------------|----------------|------------------|-------|
| Contrato existente | `Contract::factory()` | ACTIVO | No debe tener solicitud pendiente |
| Usuario autenticado | `User::factory()` | Activo | Permiso: `contracts.cancel.request` |

### ⏳ Tests Propuestos (8 casos)

1. Crea solicitud de cancelación válida
2. Rechaza solicitud sin motivo
3. Rechaza solicitud de contrato ya CANCELADO
4. Rechaza solicitud duplicada (ya existe pendiente)
5. Rechaza archivo mayor a 5MB
6. Rechaza formato de archivo no permitido
7. SQL Injection en motivo
8. XSS en motivo

**Prioridad:** 🟡 MEDIA

---

## 5️⃣ MÉTODO: `approveCancellation()` - Aprobar Cancelación

### 📍 Ubicación
**Archivo:** `app/Http/Controllers/ContractController.php` (línea 167)

### 💻 Código del Método

```php
public function approveCancellation(Request $request, int $contract, int $cancellationRequest)
{
    // Validar permisos
    // Aprobar solicitud
    // Cambiar estado contrato a CANCELADO
    // Cancelar pedidos pendientes
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Estado requerido | Notas |
|------------|----------------|------------------|-------|
| Contrato existente | `Contract::factory()` | ACTIVO | - |
| Solicitud cancelación | `CancellationRequest` | PENDIENTE | - |
| Usuario autenticado | `User::factory()` | Gerente | Permiso: `contracts.cancel.approve` |

### ⏳ Tests Propuestos (10 casos)

1. Aprueba cancelación exitosamente
2. Cambia estado contrato a CANCELADO
3. Cancela pedidos de instalación pendientes
4. Rechaza aprobar sin permiso
5. Rechaza aprobar solicitud ya procesada
6. Rechaza aprobar de contrato CANCELADO
7. Genera log de aprobación
8. Valida usuario gerente
9. Valida observaciones requeridas
10. Valida que no haya pagos pendientes

**Prioridad:** 🟠 ALTA (RF-CON-17, RF-CON-24)

---

## 📊 RESUMEN DE TESTS

| Método | Tests Implementados | Tests Propuestos | Prioridad | RF |
|--------|--------------------:|------------------:|-----------|-----|
| `store()` | **36** ✅ | 0 | ✅ COMPLETO | RF-CON-01 |
| `update()` | 0 | 15 | 🟠 ALTA | RF-CON-13 |
| `reactivate()` | 0 | 12 | 🟠 ALTA | RF-CON-14, 15 |
| `requestCancellation()` | 0 | 8 | 🟡 MEDIA | RF-CON-17 |
| `approveCancellation()` | 0 | 10 | 🟠 ALTA | RF-CON-17, 24 |
| **TOTAL** | **36** | **45** | - | - |

---

## 🎯 ORDEN DE IMPLEMENTACIÓN RECOMENDADO

### ✅ FASE 1 - COMPLETA
- [x] `store()` - 36 tests (100% protegido)

### 🔄 FASE 2 - PRÓXIMA
1. `reactivate()` - 12 tests (RF crítico, lógica compleja)
2. `update()` - 15 tests (método muy usado)
3. `approveCancellation()` - 10 tests (flujo de cancelación)

### 🔄 FASE 3 - FINAL
4. `requestCancellation()` - 8 tests (complementa cancelación)

---

## 📁 ESTRUCTURA DE ARCHIVOS

```
phpunittestsinforme/
├── CONTRATOS_PLANIFICACION.md          ← Este documento
├── Contratos/
│   ├── ContractStoreTest.php           ← ✅ Implementado (36 tests)
│   ├── ContractUpdateTest.php          ← ⏳ Pendiente (15 tests)
│   ├── ContractReactivateTest.php      ← ⏳ Pendiente (12 tests)
│   ├── ContractCancellationTest.php    ← ⏳ Pendiente (18 tests)
│   └── README.md                       ← Resumen ejecutivo
```

---

## 🔗 REFERENCIAS

- **Guía de Métodos:** `Guia_Metodos_Pruebas_PHPUnit_Lunnys.md`
- **Mapeo EDT:** `testsphpunit/MAPEO_EDT_COMPLETO.md`
- **Tests Implementados:** `testsphpunit/Contratos/ContractStoreTest.php`

---

**Documento:** Planificación de Tests - Módulo Contratos  
**Fecha:** 2024-10-14  
**Tests Implementados:** 36/81 (44.4%)  
**Estado:** FASE 1 completa, FASE 2 pendiente
