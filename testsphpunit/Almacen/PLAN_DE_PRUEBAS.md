# 💥 PLAN DE DESTRUCCIÓN - MÓDULO ALMACÉN

**Objetivo:** Destruir la gestión de inventario con casos de concurrencia, stock negativo y asignaciones simultáneas.

---

## 📊 Resumen del Módulo

| Submódulo | Implementados | Faltantes | Cobertura |
|-----------|--------------|-----------|-----------|
| **Equipos** | ✅ 17/18 | ❌ 1/18 | 94.4% |
| **Materiales** | ✅ 11/11 | ❌ 0/11 | 100% |
| **Kardex** | ✅ 12/12 | ❌ 0/12 | 100% |
| **TOTAL** | ✅ 40/41 | ❌ 1/41 | **97.6%** |

**Estado:** ⚠️ FALTA MÉTODO CRÍTICO: `preventDuplicateAssignment`

---

## 🎯 EQUIPOS - Métodos Críticos a Torturar

### 1. **store** - Registrar Equipo (RF-EQU-01)
**Ubicación:** `EquipmentController::store()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Registrar equipo válido | Datos completos | HTTP 201, equipo creado | CRÍTICO |
| 2 | Número serie duplicado | mac_address existente | HTTP 422, "Ya existe" | CRÍTICO |
| 3 | Número serie SQL injection | mac_address = "'; DROP--" | Datos escapados | CRÍTICO |
| 4 | Modelo con caracteres especiales | modelo = "ONT-2000 (G/PON) [5GHz]" | Datos guardados correctamente | ALTA |
| 5 | Número serie vacío | mac_address = "" | HTTP 422, campo requerido | ALTA |
| 6 | Número serie muy largo | mac_address = str_repeat('A', 500) | HTTP 422, límite | MEDIA |
| 7 | Estado inicial incorrecto | estado = "INVALID" | HTTP 422, enum válido | ALTA |
| 8 | Tipo equipo inválido | tipo = "SPACESHIP" | HTTP 422, validación | ALTA |
| 9 | Mass assignment malicioso | id=999, created_at='2020-01-01' | Campos protegidos ignorados | ALTA |
| 10 | Registros masivos concurrentes | 100 equipos simultáneos | Todos con códigos únicos | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
[
    'tipo' => 'ONT',
    'marca' => 'HUAWEI',
    'modelo' => 'HG8546M',
    'numero_serie' => 'SN-12345-ABCDE',
    'mac_address' => '00:1A:2B:3C:4D:5E',
    'estado' => 'DISPONIBLE',
    'observaciones' => 'Equipo nuevo'
]

// CASO CRÍTICO: MAC duplicada (race condition)
Equipment::factory()->create(['mac_address' => '00:1A:2B:3C:4D:5E']);
// Thread 1 y 2 intentan crear con misma MAC simultáneamente

// CASO MALICIOSO: SQL Injection
[
    'numero_serie' => "'; DROP TABLE equipment; --",
    'mac_address' => "00:1A'; DELETE FROM equipment WHERE '1'='1",
    'observaciones' => "'; UPDATE equipment SET estado='EN_USO'; --"
]

// CASO EDGE: Caracteres especiales
[
    'modelo' => "ONT-2000 (5GHz) [WiFi 6] / AX3000 <Nuevo>",
    'observaciones' => "Equipo @ Almacén #2 - Pasillo B (nivel 3)"
]

// CASO EXTREMO: Strings muy largos
[
    'numero_serie' => str_repeat('A', 1000),
    'observaciones' => str_repeat('B', 10000)
]

// CASO MASS ASSIGNMENT: Intentar modificar campos protegidos
[
    'id' => 99999,
    'codigo' => 'CUSTOM-CODE',
    'created_at' => '2020-01-01',
    'updated_at' => '2020-01-01'
]
```

---

### 2. **assign** - Asignar Equipo a Instalación (RF-EQU-08) 🔴 CRÍTICO
**Ubicación:** `EquipmentController::assign()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Asignar equipo DISPONIBLE | Equipo disponible + instalación válida | Estado → EN_USO | CRÍTICO |
| 2 | Asignar equipo ya EN_USO | Equipo asignado a otra instalación | HTTP 422, "Ya asignado" | CRÍTICO |
| 3 | Asignar equipo MANTENIMIENTO | Estado: MANTENIMIENTO | HTTP 422, "No disponible" | ALTA |
| 4 | Asignar equipo DADO_BAJA | Estado: DADO_BAJA | HTTP 422, "Equipo dado de baja" | ALTA |
| 5 | Asignar a instalación inexistente | installation_id = 99999 | HTTP 422, validación | ALTA |
| 6 | Asignar a instalación COMPLETADA | Instalación ya completada | HTTP 422, "Instalación finalizada" | ALTA |
| 7 | Asignación duplicada simultánea 🚨 | 2 usuarios asignan mismo equipo | Solo 1 éxito (FALTA MÉTODO) | CRÍTICO |
| 8 | Asignar múltiples equipos a 1 instalación | 5 equipos misma instalación | Todos asignados | ALTA |
| 9 | Asignar mismo equipo a 2 instalaciones | 2 instalaciones concurrentes | Solo 1 éxito | CRÍTICO |
| 10 | Registro en Kardex al asignar | Equipo asignado | Movimiento ASIGNACION en Kardex | CRÍTICO |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$equipment = Equipment::factory()->create(['estado' => 'DISPONIBLE']);
$installation = Installation::factory()->create(['estado' => 'PENDIENTE']);
$response = $this->post("/equipment/{$equipment->id}/assign", [
    'installation_id' => $installation->id
]);

// CASO CRÍTICO: Doble asignación (race condition) 🚨
$equipment = Equipment::factory()->create(['estado' => 'DISPONIBLE']);
$installation1 = Installation::factory()->create();
$installation2 = Installation::factory()->create();
// Thread 1: assign(equipment, installation1)
// Thread 2: assign(equipment, installation2)
// PROBLEMA: Sin preventDuplicateAssignment, ambos pueden tener éxito

// CASO EDGE: Asignar equipo ya asignado a otra instalación
$equipment = Equipment::factory()->create(['estado' => 'EN_USO']);
Installation::factory()->create([
    'equipment_id' => $equipment->id,
    'estado' => 'EN_PROGRESO'
]);
$newInstallation = Installation::factory()->create();
$this->post("/equipment/{$equipment->id}/assign", [
    'installation_id' => $newInstallation->id
]);
// Debe rechazar

// CASO EXTREMO: Asignar 1000 equipos simultáneamente
$equipment = Equipment::factory()->count(1000)->create(['estado' => 'DISPONIBLE']);
$installations = Installation::factory()->count(1000)->create();
// Asignar todos en paralelo
```

---

### 3. **returnEquipment** - Registrar Devolución (RF-EQU-11)
**Ubicación:** `EquipmentController::returnEquipment()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Devolver equipo EN_USO | Equipo asignado + condición BUENO | Estado → DISPONIBLE | CRÍTICO |
| 2 | Devolver equipo DISPONIBLE | Estado: DISPONIBLE | HTTP 422, "No está en uso" | ALTA |
| 3 | Devolver equipo MANTENIMIENTO | Estado: MANTENIMIENTO | HTTP 422, estado inválido | MEDIA |
| 4 | Devolver sin condición | condicion = null | HTTP 422, campo requerido | ALTA |
| 5 | Devolver condición MALO | condicion = MALO | Estado → MANTENIMIENTO | CRÍTICO |
| 6 | Devolver condición INOPERATIVO | condicion = INOPERATIVO | Estado → DADO_BAJA | ALTA |
| 7 | Registro en Kardex | Equipo devuelto | Movimiento DEVOLUCION en Kardex | CRÍTICO |
| 8 | Devolver con observaciones SQL | observaciones = "'; DROP--" | Datos escapados | ALTA |
| 9 | Devolver 100 equipos masivamente | Devolución masiva | Todos procesados | MEDIA |
| 10 | Doble devolución concurrente | 2 usuarios devuelven mismo equipo | Solo 1 éxito | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO: Devolución en buen estado
$equipment = Equipment::factory()->create(['estado' => 'EN_USO']);
$this->post("/equipment/{$equipment->id}/return", [
    'condicion' => 'BUENO',
    'observaciones' => 'Equipo en perfectas condiciones'
]);
// Estado debe cambiar a DISPONIBLE

// CASO CRÍTICO: Devolución en mal estado
$equipment = Equipment::factory()->create(['estado' => 'EN_USO']);
$this->post("/equipment/{$equipment->id}/return", [
    'condicion' => 'MALO',
    'observaciones' => 'Equipo con fallas'
]);
// Estado debe cambiar a MANTENIMIENTO

// CASO EDGE: Devolver equipo nunca asignado
$equipment = Equipment::factory()->create(['estado' => 'DISPONIBLE']);
$this->post("/equipment/{$equipment->id}/return", [
    'condicion' => 'BUENO'
]);
// Debe rechazar

// CASO CONCURRENCIA
$equipment = Equipment::factory()->create(['estado' => 'EN_USO']);
// Thread 1: POST /equipment/{id}/return
// Thread 2: POST /equipment/{id}/return (al mismo tiempo)
```

---

## 🎯 MATERIALES - Métodos Críticos a Torturar

### 1. **store** - Registrar Material (RF-MAT-01)
**Ubicación:** `MaterialController::store()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Registrar material válido | Datos completos | HTTP 201, material creado | CRÍTICO |
| 2 | Código duplicado | codigo existente | HTTP 422, "Ya existe" | CRÍTICO |
| 3 | Stock inicial negativo | stock_actual = -10 | HTTP 422, validación | ALTA |
| 4 | Stock inicial decimal | stock_actual = 10.5 | Depende unidad_medida | MEDIA |
| 5 | Stock mínimo negativo | stock_minimo = -5 | HTTP 422, validación | ALTA |
| 6 | Unidad medida inválida | unidad_medida = "INVALID" | HTTP 422, enum válido | ALTA |
| 7 | Nombre SQL injection | nombre = "'; DROP--" | Datos escapados | CRÍTICO |
| 8 | Descripción XSS | descripcion = "<script>alert()</script>" | Datos sanitizados | ALTA |
| 9 | Precio unitario negativo | precio_unitario = -50.00 | HTTP 422, validación | ALTA |
| 10 | Registros masivos concurrentes | 100 materiales simultáneos | Todos con códigos únicos | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
[
    'codigo' => 'MAT-CABLE-001',
    'nombre' => 'Cable de Fibra Óptica',
    'descripcion' => 'Cable de fibra monomodo SM 2.0mm',
    'unidad_medida' => 'METROS',
    'stock_actual' => 1000,
    'stock_minimo' => 100,
    'precio_unitario' => 2.50
]

// CASO MALICIOSO
[
    'codigo' => "'; DROP TABLE materials; --",
    'nombre' => "<script>alert('XSS')</script>",
    'descripcion' => "'; DELETE FROM materials WHERE 1=1; --"
]

// CASO EDGE: Stock decimal
[
    'unidad_medida' => 'METROS',
    'stock_actual' => 50.75  // ¿Se permite?
]
[
    'unidad_medida' => 'UNIDADES',
    'stock_actual' => 10.5  // No debe permitirse
]

// CASO EXTREMO: Código muy largo
[
    'codigo' => str_repeat('A', 500)
]
```

---

### 2. **checkLowStock** - Alertar Stock Mínimo (RF-MAT-09)
**Ubicación:** `MaterialController::checkLowStock()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Material con stock bajo | stock_actual < stock_minimo | Alerta generada | CRÍTICO |
| 2 | Material con stock suficiente | stock_actual >= stock_minimo | Sin alerta | ALTA |
| 3 | Material con stock = mínimo | stock_actual = stock_minimo | Sin alerta (límite) | ALTA |
| 4 | Material stock = 0 | stock_actual = 0 | Alerta crítica | CRÍTICO |
| 5 | Material stock negativo | stock_actual = -10 | Alerta + error | ALTA |
| 6 | Consultar todos los stock bajos | GET /materials/low-stock | Lista correcta | ALTA |
| 7 | Performance con 10000 materiales | 10000 materiales | Respuesta < 1 seg | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO CRÍTICO: Stock bajo
Material::factory()->create([
    'stock_actual' => 5,
    'stock_minimo' => 10
]);
$lowStock = Material::checkLowStock();
// Debe aparecer en el listado

// CASO EDGE: Stock exacto en mínimo
Material::factory()->create([
    'stock_actual' => 10,
    'stock_minimo' => 10
]);
// ¿Se considera bajo? Depende del criterio (< vs <=)

// CASO EXTREMO: Stock negativo (error en Kardex)
Material::factory()->create([
    'stock_actual' => -5,
    'stock_minimo' => 10
]);
```

---

## 🎯 KARDEX - Métodos Críticos a Torturar

### 1. **storeEntry** - Registrar Entrada (RF-KAR-01)
**Ubicación:** `KardexController::storeEntry()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Entrada material válida | Material + cantidad + responsable | Stock actualizado | CRÍTICO |
| 2 | Entrada cantidad negativa | cantidad = -10 | HTTP 422, validación | ALTA |
| 3 | Entrada cantidad 0 | cantidad = 0 | HTTP 422, "Cantidad > 0" | ALTA |
| 4 | Entrada material inexistente | material_id = 99999 | HTTP 422, validación | ALTA |
| 5 | Entrada sin responsable | responsable_id = null | HTTP 422, campo requerido | ALTA |
| 6 | Entrada actualiza stock | Entrada 100 unidades | stock_actual += 100 | CRÍTICO |
| 7 | Entradas concurrentes mismo material | 2 entradas simultáneas | Stock correcto | CRÍTICO |
| 8 | Entrada masiva | 1000 entradas | Todas procesadas | MEDIA |
| 9 | Entrada con documento SQL | documento = "'; DROP--" | Datos escapados | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$material = Material::factory()->create(['stock_actual' => 100]);
$response = $this->post('/kardex/entry', [
    'material_id' => $material->id,
    'tipo_movimiento' => 'ENTRADA',
    'cantidad' => 50,
    'documento' => 'FACTURA-001',
    'responsable_id' => 1
]);
// stock_actual debe ser 150

// CASO CRÍTICO: Concurrencia (race condition)
$material = Material::factory()->create(['stock_actual' => 100]);
// Thread 1: POST /kardex/entry (cantidad: 50)
// Thread 2: POST /kardex/entry (cantidad: 30)
// Stock final debe ser: 100 + 50 + 30 = 180

// CASO EDGE: Entrada cantidad decimal
$this->post('/kardex/entry', [
    'material_id' => $material->id,
    'cantidad' => 10.5
]);
// ¿Se permite si unidad_medida = 'METROS'?
```

---

### 2. **storeExit** - Registrar Salida (RF-KAR-02)
**Ubicación:** `KardexController::storeExit()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Salida material válida | Material + cantidad < stock | Stock disminuido | CRÍTICO |
| 2 | Salida cantidad > stock | cantidad > stock_actual | HTTP 422, "Stock insuficiente" | CRÍTICO |
| 3 | Salida deja stock = 0 | cantidad = stock_actual | Stock = 0 permitido | ALTA |
| 4 | Salida con stock = 0 | stock_actual = 0, salida 10 | HTTP 422, sin stock | CRÍTICO |
| 5 | Salida cantidad negativa | cantidad = -10 | HTTP 422, validación | ALTA |
| 6 | Salidas concurrentes | 2 salidas simultáneas misma cantidad | Verificar stock suficiente | CRÍTICO |
| 7 | Salida asociada a instalación | Salida para instalación X | instalacion_id registrado | ALTA |
| 8 | Salida masiva | 100 salidas | Stock correcto | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$material = Material::factory()->create(['stock_actual' => 100]);
$this->post('/kardex/exit', [
    'material_id' => $material->id,
    'tipo_movimiento' => 'SALIDA',
    'cantidad' => 30,
    'motivo' => 'Instalación',
    'responsable_id' => 1
]);
// stock_actual debe ser 70

// CASO CRÍTICO: Cantidad excede stock
$material = Material::factory()->create(['stock_actual' => 10]);
$this->post('/kardex/exit', [
    'material_id' => $material->id,
    'cantidad' => 50  // > 10
]);
// Debe rechazar con HTTP 422

// CASO CRÍTICO: Concurrencia (race condition)
$material = Material::factory()->create(['stock_actual' => 100]);
// Thread 1: POST /kardex/exit (cantidad: 60)
// Thread 2: POST /kardex/exit (cantidad: 60)
// Solo 1 debe tener éxito, el otro debe fallar
// Stock final: 40 (no negativo)

// CASO EDGE: Salida deja stock exacto en 0
$material = Material::factory()->create(['stock_actual' => 50]);
$this->post('/kardex/exit', [
    'cantidad' => 50
]);
// stock_actual = 0 (permitido)
```

---

### 3. **storeAdjustment** - Ajuste de Inventario (RF-KAR-06)
**Ubicación:** `KardexController::storeAdjustment()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Ajuste positivo | cantidad_ajuste = +20 | Stock aumenta | ALTA |
| 2 | Ajuste negativo | cantidad_ajuste = -10 | Stock disminuye | ALTA |
| 3 | Ajuste a 0 | cantidad_ajuste lleva a 0 | Stock = 0 | ALTA |
| 4 | Ajuste negativo excede stock | stock=10, ajuste=-20 | HTTP 422 o stock=0 | CRÍTICO |
| 5 | Ajuste sin motivo | motivo = null | HTTP 422, requerido | ALTA |
| 6 | Ajuste sin autorización | Usuario sin permisos | HTTP 403 | ALTA |
| 7 | Múltiples ajustes concurrentes | 3 ajustes simultáneos | Stock correcto | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO AJUSTE POSITIVO (corrección inventario)
$material = Material::factory()->create(['stock_actual' => 100]);
$this->post('/kardex/adjustment', [
    'material_id' => $material->id,
    'tipo_movimiento' => 'AJUSTE',
    'cantidad_ajuste' => 10,  // Encontraron 10 unidades extras
    'motivo' => 'Conteo físico'
]);
// stock_actual = 110

// CASO AJUSTE NEGATIVO (pérdida/robo)
$material = Material::factory()->create(['stock_actual' => 100]);
$this->post('/kardex/adjustment', [
    'cantidad_ajuste' => -15,  // Faltaron 15 unidades
    'motivo' => 'Pérdida en almacén'
]);
// stock_actual = 85

// CASO CRÍTICO: Ajuste negativo excesivo
$material = Material::factory()->create(['stock_actual' => 10]);
$this->post('/kardex/adjustment', [
    'cantidad_ajuste' => -50  // Excede el stock
]);
// ¿Rechazar o dejar en 0?
```

---

## 🚨 Métodos Faltantes

**✅ NINGUNO - El método preventDuplicateAssignment YA ESTÁ IMPLEMENTADO**

---

## ✅ **preventDuplicateAssignment** (RF-EQU-10) - IMPLEMENTADO

**Ubicación:** `InstallationOrderService::reserveEquipment()` (Línea 309)

**Estado:** ✅ **YA EXISTE CON `lockForUpdate()`**

```php
public function reserveEquipment(User $actor, int $id, int $equipmentId): void
{
    DB::transaction(function () use ($actor, $id, $equipmentId): void {
        $order = $this->editable($id);
        $this->requireActiveContract($order);
        
        // 🔒 LOCK IMPLEMENTADO - Previene race conditions
        $equipment = DB::table('equipos')
            ->where('equipo_id', $equipmentId)
            ->lockForUpdate()  // ← ✅ PROTECCIÓN CONTRA DOBLE ASIGNACIÓN
            ->first();
        
        // Validación estado
        if (!$equipment || $equipment->estado !== 'DISPONIBLE' || 
            in_array($equipment->condicion, ['MALO', 'MALOGRADO'], true)) {
            $this->fail('equipo_id', 'Selecciona un equipo disponible y en buen estado.');
        }
        
        // Validación de no duplicación
        if (DB::table('pedido_equipos')
            ->where('equipo_id', $equipmentId)
            ->whereIn('estado_asignacion', ['ASIGNADO', 'UTILIZADO'])
            ->whereNull('fecha_devolucion')
            ->exists()) {
            $this->fail('equipo_id', 'El equipo ya está asignado a otro pedido.');
        }
        
        // Asignación
        DB::table('pedido_equipos')->insert([
            'pedido_id' => $id,
            'equipo_id' => $equipmentId,
            'asignado_por' => $actor->getKey(),
            'estado_asignacion' => 'ASIGNADO',
        ]);
        
        // Actualizar estado del equipo
        DB::table('equipos')
            ->where('equipo_id', $equipmentId)
            ->update(['estado' => 'ASIGNADO']);
        
        // Registro en Kardex
        DB::table('movimientos_inventario')->insert([
            'tipo_movimiento' => 'ASIGNACION',
            'equipo_id' => $equipmentId,
            'pedido_id' => $id,
            'cantidad' => 1,
            'responsable_id' => $actor->getKey(),
            'observacion' => 'Equipo reservado para instalación',
        ]);
    }, 3);  // ← Reintentos automáticos en caso de deadlock
}
```

### ✅ Protecciones Implementadas:

1. **`lockForUpdate()`** - Bloquea la fila del equipo hasta que termine la transacción
2. **`DB::transaction()` con 3 reintentos** - Maneja deadlocks automáticamente
3. **Validación de estado DISPONIBLE** - Solo equipos disponibles
4. **Validación de no duplicación** - Verifica que no esté ya asignado
5. **Registro atómico** - Todo o nada (pedido_equipos + estado + kardex)

### 🎯 Flujo Protegido:

```
Thread 1 (Usuario A):
⏰ 10:00:00.000 - SELECT ... WHERE equipo_id=123 FOR UPDATE  🔒
⏰ 10:00:00.005 - Valida estado DISPONIBLE ✓
⏰ 10:00:00.010 - Asigna equipo a Instalación A
⏰ 10:00:00.015 - UPDATE estado='ASIGNADO'
⏰ 10:00:00.020 - COMMIT 🔓

Thread 2 (Usuario B):
⏰ 10:00:00.001 - SELECT ... WHERE equipo_id=123 FOR UPDATE  ⏸️ ESPERA
⏰ 10:00:00.021 - (Después del COMMIT de Thread 1) 🔓
⏰ 10:00:00.022 - Valida estado... ❌ Ya es ASIGNADO
⏰ 10:00:00.023 - ROLLBACK - Error 422
```

### 📊 Resultado:
✅ **Solo 1 asignación exitosa**  
✅ **Sin equipos duplicados**  
✅ **Datos consistentes en Kardex**

---

## 📝 Tests Recomendados

Aunque el método está implementado, se deben crear tests para validar:

```php
public function test_no_permite_asignacion_duplicada_concurrente()
{
    $equipment = Equipment::factory()->create(['estado' => 'DISPONIBLE']);
    $order1 = Installation::factory()->create();
    $order2 = Installation::factory()->create();
    
    // Simular 2 asignaciones simultáneas
    $response1 = $this->postAsync("/orders/{$order1->id}/equipment", [
        'equipo_id' => $equipment->id
    ]);
    $response2 = $this->postAsync("/orders/{$order2->id}/equipment", [
        'equipo_id' => $equipment->id
    ]);
    
    // Esperar respuestas
    [$result1, $result2] = collect([$response1, $response2])
        ->map(fn($r) => $r->wait())
        ->all();
    
    // ✅ Solo 1 debe tener éxito
    $successCount = collect([$result1, $result2])
        ->filter(fn($r) => $r->status() === 200)
        ->count();
    
    $this->assertEquals(1, $successCount);
    
    // ✅ Verificar que solo hay 1 asignación
    $assignments = DB::table('pedido_equipos')
        ->where('equipo_id', $equipment->id)
        ->whereNull('fecha_devolucion')
        ->count();
    
    $this->assertEquals(1, $assignments);
}
```

---

## 📝 Comandos de Ejecución

```bash
# Equipos
php artisan test testsphpunit/Almacen/EquipmentTest.php

# Materiales
php artisan test testsphpunit/Almacen/MaterialTest.php

# Kardex
php artisan test testsphpunit/Almacen/KardexTest.php

# Todos
php artisan test testsphpunit/Almacen/
```

---

## 🎯 Objetivos de Cobertura

- **Equipos:** 100% (implementar preventDuplicateAssignment)
- **Materiales:** 95%+
- **Kardex:** 95%+
- **Concurrencia:** 100% de casos críticos
- **Validación stock:** 100%

---

## 📊 Prioridades

1. **CRÍTICO:** assign (con lock), storeExit (validar stock), preventDuplicateAssignment
2. **ALTA:** store (equipos/materiales), returnEquipment, checkLowStock
3. **MEDIA:** Kardex masivo, performance
