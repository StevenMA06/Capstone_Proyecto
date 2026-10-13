# 💥 PLAN DE DESTRUCCIÓN - MÓDULO INSTALACIONES

**Objetivo:** Romper el flujo de instalaciones con casos de estados inconsistentes, archivos corruptos y concurrencia.

---

## 📊 Resumen del Módulo

| Submódulo | Implementados | Faltantes | Cobertura |
|-----------|--------------|-----------|-----------|
| **Pedidos** | ✅ 13/13 | ❌ 0/13 | 100% |
| **Ejecución** | ✅ 14/14 | ❌ 0/14 | 100% |
| **TOTAL** | ✅ 27/27 | ❌ 0/27 | **100%** |

**Estado:** ✅ TODOS LOS MÉTODOS IMPLEMENTADOS

---

## 🎯 PEDIDOS - Métodos Críticos a Torturar

### 1. **store** - Registrar Pedido de Instalación (RF-PED-01)
**Ubicación:** `InstallationOrderController::store()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Pedido válido con contrato activo | Contrato ACTIVO + técnico + fecha | HTTP 201, pedido creado | CRÍTICO |
| 2 | Pedido sin contrato activo | Contrato PENDIENTE | HTTP 422, "Activar primero" | CRÍTICO |
| 3 | Pedido contrato CANCELADO | Estado: CANCELADO | HTTP 422, contrato inválido | ALTA |
| 4 | Pedido sin técnico asignado | tecnico_id = null | HTTP 422 o asignar después | MEDIA |
| 5 | Técnico inexistente | tecnico_id = 99999 | HTTP 422, validación | ALTA |
| 6 | Técnico inactivo | Empleado con fecha_baja | HTTP 422, "Técnico inactivo" | ALTA |
| 7 | Fecha programada pasada | fecha_programada = ayer | HTTP 422, fecha inválida | ALTA |
| 8 | Fecha programada muy futura | fecha_programada = +2 años | HTTP 422 o permitir | MEDIA |
| 9 | Pedido duplicado mismo contrato | Mismo contrato + fecha | HTTP 422 o permitir | MEDIA |
| 10 | Asignar equipos inexistentes | equipment_ids = [99999] | HTTP 422, validación | ALTA |
| 11 | Asignar equipos no disponibles | Equipos EN_USO | HTTP 422, "Equipo no disponible" | CRÍTICO |
| 12 | Asignar materiales sin stock | Material stock = 0 | HTTP 422, "Sin stock" | CRÍTICO |
| 13 | Crear pedido reserva stock | Materiales asignados | Stock reservado | ALTA |
| 14 | SQL injection en observaciones | observaciones = "'; DROP--" | Datos escapados | ALTA |
| 15 | Crear 100 pedidos concurrentes | 100 requests simultáneos | Todos con código único | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$contract = Contract::factory()->create(['estado' => 'ACTIVO']);
$technician = Employee::factory()->create([
    'cargo' => 'TECNICO',
    'fecha_baja' => null
]);
$equipment = Equipment::factory()->count(2)->create(['estado' => 'DISPONIBLE']);
$materials = Material::factory()->count(3)->create(['stock_actual' => 100]);

$response = $this->post('/installation-orders', [
    'contract_id' => $contract->id,
    'tecnico_id' => $technician->id,
    'fecha_programada' => now()->addDays(3)->format('Y-m-d'),
    'equipment_ids' => $equipment->pluck('id')->toArray(),
    'materials' => $materials->map(fn($m) => [
        'material_id' => $m->id,
        'cantidad' => 10
    ])->toArray(),
    'observaciones' => 'Instalación programada'
]);

// CASO CRÍTICO: Contrato no activo
$contract = Contract::factory()->create(['estado' => 'PENDIENTE']);
$this->post('/installation-orders', ['contract_id' => $contract->id]);
// Debe rechazar

// CASO CRÍTICO: Equipos no disponibles
$equipment = Equipment::factory()->create(['estado' => 'EN_USO']);
$this->post('/installation-orders', [
    'equipment_ids' => [$equipment->id]
]);
// Debe rechazar

// CASO CRÍTICO: Sin stock suficiente
$material = Material::factory()->create(['stock_actual' => 5]);
$this->post('/installation-orders', [
    'materials' => [
        ['material_id' => $material->id, 'cantidad' => 10]  // Requiere 10, solo hay 5
    ]
]);
// Debe rechazar

// CASO EDGE: Fecha en día no laboral
$this->post('/installation-orders', [
    'fecha_programada' => '2026-12-25'  // Navidad
]);
// ¿Permitir o alertar?

// CASO MALICIOSO
$this->post('/installation-orders', [
    'observaciones' => "'; DROP TABLE installation_orders; --",
    'estado' => 'COMPLETADO'  // Intentar mass assignment
]);
```

---

### 2. **assignTechnician** - Asignar Técnico (RF-PED-06)
**Ubicación:** `InstallationOrderController::assignTechnician()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Asignar técnico válido | Pedido PENDIENTE + técnico activo | Técnico asignado | CRÍTICO |
| 2 | Asignar a pedido COMPLETADO | Estado: COMPLETADO | HTTP 422, "Ya finalizado" | ALTA |
| 3 | Asignar a pedido CANCELADO | Estado: CANCELADO | HTTP 422, estado inválido | ALTA |
| 4 | Técnico inexistente | tecnico_id = 99999 | HTTP 422, validación | ALTA |
| 5 | Técnico inactivo | fecha_baja != null | HTTP 422, "Inactivo" | ALTA |
| 6 | Empleado no técnico | cargo = 'VENDEDOR' | HTTP 422, "No es técnico" | ALTA |
| 7 | Reasignar técnico | Cambiar técnico asignado | Permitido | MEDIA |
| 8 | Técnico con 10+ instalaciones | Sobrecarga de trabajo | Warning o permitir | BAJA |
| 9 | Enviar notificación a técnico | Técnico asignado | Email/notificación enviada | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$order = Installation::factory()->create(['estado' => 'PENDIENTE']);
$technician = Employee::factory()->create([
    'cargo' => 'TECNICO',
    'fecha_baja' => null
]);
$this->patch("/installation-orders/{$order->id}/assign-technician", [
    'tecnico_id' => $technician->id
]);

// CASO EDGE: Reasignar técnico
$order = Installation::factory()->create([
    'tecnico_id' => 1,  // Técnico anterior
    'estado' => 'PENDIENTE'
]);
$this->patch("/installation-orders/{$order->id}/assign-technician", [
    'tecnico_id' => 2  // Nuevo técnico
]);

// CASO EXTREMO: Técnico con muchas instalaciones
$technician = Employee::factory()->create();
Installation::factory()->count(15)->create([
    'tecnico_id' => $technician->id,
    'estado' => 'PENDIENTE'
]);
// ¿Alertar sobrecarga?
```

---

### 3. **reschedule** - Reprogramar Pedido (RF-PED-12)
**Ubicación:** `InstallationOrderController::reschedule()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Reprogramar pedido PENDIENTE | Nueva fecha válida | fecha_programada actualizada | CRÍTICO |
| 2 | Reprogramar pedido EN_PROGRESO | Estado: EN_PROGRESO | HTTP 422, "En ejecución" | ALTA |
| 3 | Reprogramar pedido COMPLETADO | Estado: COMPLETADO | HTTP 422, "Ya finalizado" | ALTA |
| 4 | Nueva fecha pasada | fecha_programada = ayer | HTTP 422, fecha inválida | ALTA |
| 5 | Nueva fecha = fecha actual | Mismo día | Permitir | MEDIA |
| 6 | Reprogramar sin motivo | motivo = null | HTTP 422, requerido | ALTA |
| 7 | Estado cambia a REPROGRAMADO | Pedido reprogramado | Estado actualizado | CRÍTICO |
| 8 | Notificar cliente y técnico | Reprogramación exitosa | Emails enviados | MEDIA |
| 9 | Reprogramar múltiples veces | 5 reprogramaciones | Todas registradas | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$order = Installation::factory()->create([
    'estado' => 'PENDIENTE',
    'fecha_programada' => now()->addDays(5)
]);
$this->post("/installation-orders/{$order->id}/reschedule", [
    'nueva_fecha' => now()->addDays(10)->format('Y-m-d'),
    'motivo' => 'Cliente solicitó cambio'
]);

// CASO EDGE: Reprogramar para hoy
$this->post("/installation-orders/{$order->id}/reschedule", [
    'nueva_fecha' => now()->format('Y-m-d'),
    'motivo' => 'Urgente'
]);

// CASO EDGE: Reprogramar fecha pasada
$this->post("/installation-orders/{$order->id}/reschedule", [
    'nueva_fecha' => now()->subDays(1)->format('Y-m-d')
]);
```

---

## 🎯 EJECUCIÓN - Métodos Críticos a Torturar

### 1. **startInstallation** - Iniciar Instalación (RF-INS-03)
**Ubicación:** `OfficeInstallationCoordinationController::startInstallation()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Iniciar instalación PENDIENTE | Pedido válido + técnico correcto | Estado → EN_PROGRESO | CRÍTICO |
| 2 | Iniciar instalación ya EN_PROGRESO | Estado: EN_PROGRESO | HTTP 422, "Ya iniciada" | ALTA |
| 3 | Iniciar instalación COMPLETADA | Estado: COMPLETADO | HTTP 422, "Ya finalizada" | ALTA |
| 4 | Técnico diferente inicia | Usuario != técnico asignado | HTTP 403, "No asignado" | ALTA |
| 5 | Registrar hora inicio | Instalación iniciada | hora_inicio guardada | CRÍTICO |
| 6 | Equipos pasan a EN_USO | Instalación iniciada | Equipos actualizados | CRÍTICO |
| 7 | Materiales se descontaron | Instalación iniciada | Stock disminuido | CRÍTICO |
| 8 | Inicio sin equipos asignados | equipment_ids vacío | HTTP 422 o permitir | MEDIA |
| 9 | Iniciar múltiples simultáneas | Mismo técnico, diferentes pedidos | Permitir | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$technician = Employee::factory()->create(['cargo' => 'TECNICO']);
$equipment = Equipment::factory()->count(2)->create(['estado' => 'DISPONIBLE']);
$material = Material::factory()->create(['stock_actual' => 100]);

$order = Installation::factory()->create([
    'tecnico_id' => $technician->id,
    'estado' => 'PENDIENTE'
]);
$order->equipment()->attach($equipment->pluck('id'));
$order->materials()->attach($material->id, ['cantidad' => 20]);

$this->actingAs($technician->user)
    ->post("/installations/{$order->id}/start");

// Verificar:
// - Estado = EN_PROGRESO
// - hora_inicio != null
// - Equipos estado = EN_USO
// - Material stock = 80 (100 - 20)

// CASO CRÍTICO: Técnico incorrecto intenta iniciar
$otherTechnician = Employee::factory()->create(['cargo' => 'TECNICO']);
$this->actingAs($otherTechnician->user)
    ->post("/installations/{$order->id}/start");
// Debe rechazar con HTTP 403

// CASO EDGE: Stock insuficiente al iniciar
$material = Material::factory()->create(['stock_actual' => 5]);
$order->materials()->attach($material->id, ['cantidad' => 10]);
$this->post("/installations/{$order->id}/start");
// ¿Rechazar o permitir con alerta?
```

---

### 2. **completeInstallation** - Finalizar Instalación (RF-INS-12)
**Ubicación:** `OfficeInstallationCoordinationController::completeInstallation()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Completar instalación EN_PROGRESO | Estado válido + evidencias | Estado → COMPLETADO | CRÍTICO |
| 2 | Completar instalación PENDIENTE | Estado: PENDIENTE | HTTP 422, "Iniciar primero" | ALTA |
| 3 | Completar instalación ya COMPLETADA | Estado: COMPLETADO | HTTP 422, "Ya finalizada" | ALTA |
| 4 | Sin evidencia fotográfica | photo = null | HTTP 422, requerido | ALTA |
| 5 | Sin evidencia contractual | contract_evidence = null | HTTP 422, requerido | ALTA |
| 6 | Archivo muy grande | photo = 50MB | HTTP 422, límite excedido | ALTA |
| 7 | Archivo formato inválido | photo = .exe | HTTP 422, solo imágenes | ALTA |
| 8 | Registrar hora finalización | Instalación completada | hora_fin guardada | CRÍTICO |
| 9 | Calcular duración | hora_inicio + hora_fin | duración calculada | MEDIA |
| 10 | Contrato pasa a ACTIVO | Instalación completada | Contrato activado | CRÍTICO |
| 11 | Generar pagos automáticos | Contrato activado | Pagos generados | CRÍTICO |
| 12 | Técnico diferente completa | Usuario != técnico asignado | HTTP 403, Forbidden | ALTA |
| 13 | XSS en observaciones | observaciones = "<script>" | Datos sanitizados | ALTA |
| 14 | Archivo corrupto | Imagen corrupta | Validar integridad | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$order = Installation::factory()->create([
    'estado' => 'EN_PROGRESO',
    'hora_inicio' => now()->subHours(2)
]);

$photo = UploadedFile::fake()->image('evidencia.jpg', 1920, 1080);
$contractEvidence = UploadedFile::fake()->image('contrato.jpg');

$response = $this->post("/installations/{$order->id}/complete", [
    'photo' => $photo,
    'contract_evidence' => $contractEvidence,
    'observaciones' => 'Instalación exitosa',
    'equipment_used_ids' => [1, 2],
    'materials_used' => [
        ['material_id' => 1, 'cantidad' => 20],
        ['material_id' => 2, 'cantidad' => 5]
    ]
]);

// Verificar:
// - Estado = COMPLETADO
// - hora_fin guardada
// - Archivos almacenados
// - Contrato estado = ACTIVO
// - Pagos generados (12 si contrato es anual)

// CASO CRÍTICO: Sin evidencias
$this->post("/installations/{$order->id}/complete", [
    'observaciones' => 'Sin fotos'
]);
// Debe rechazar

// CASO EDGE: Archivo muy grande
$largePhoto = UploadedFile::fake()->create('huge.jpg', 50000); // 50MB
$this->post("/installations/{$order->id}/complete", [
    'photo' => $largePhoto
]);
// Debe rechazar

// CASO MALICIOSO: Archivo ejecutable
$maliciousFile = UploadedFile::fake()->create('virus.exe', 100);
$this->post("/installations/{$order->id}/complete", [
    'photo' => $maliciousFile
]);
// Debe rechazar

// CASO XSS
$this->post("/installations/{$order->id}/complete", [
    'photo' => UploadedFile::fake()->image('test.jpg'),
    'contract_evidence' => UploadedFile::fake()->image('test.jpg'),
    'observaciones' => '<script>alert("XSS")</script>'
]);
// Datos deben sanitizarse
```

---

### 3. **uploadPhoto** - Adjuntar Evidencia Fotográfica (RF-INS-10)
**Ubicación:** `OfficeInstallationCoordinationController::uploadPhoto()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Subir imagen válida | JPG 1920x1080 | Archivo guardado | CRÍTICO |
| 2 | Subir imagen PNG | PNG válido | Archivo guardado | ALTA |
| 3 | Subir archivo no imagen | PDF, TXT, EXE | HTTP 422, formato inválido | CRÍTICO |
| 4 | Imagen muy grande | 50MB | HTTP 422, límite excedido | ALTA |
| 5 | Imagen muy pequeña | 1KB (10x10px) | Permitir o rechazar | MEDIA |
| 6 | Nombre archivo SQL injection | "'; DROP--.jpg" | Nombre sanitizado | ALTA |
| 7 | Nombre archivo path traversal | "../../etc/passwd.jpg" | Nombre sanitizado | CRÍTICO |
| 8 | Imagen corrupta | Archivo dañado | Validar integridad | MEDIA |
| 9 | Subir múltiples fotos | 10 imágenes | Todas guardadas | MEDIA |
| 10 | Reemplazar foto existente | Nueva imagen | Sobrescribir | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$photo = UploadedFile::fake()->image('instalacion.jpg', 1920, 1080);
$this->post("/installations/{$order->id}/upload-photo", [
    'photo' => $photo
]);

// CASO CRÍTICO: Archivo no imagen
$pdf = UploadedFile::fake()->create('document.pdf', 500);
$this->post("/installations/{$order->id}/upload-photo", [
    'photo' => $pdf
]);
// Debe rechazar

// CASO CRÍTICO: Path traversal
$maliciousFile = UploadedFile::fake()->image('../../shell.jpg');
$this->post("/installations/{$order->id}/upload-photo", [
    'photo' => $maliciousFile
]);
// Nombre debe sanitizarse

// CASO EDGE: Imagen muy grande
$hugeImage = UploadedFile::fake()->create('huge.jpg', 50000); // 50MB
$this->post("/installations/{$order->id}/upload-photo", [
    'photo' => $hugeImage
]);

// CASO EXTREMO: Múltiples uploads concurrentes
// 5 técnicos subiendo fotos al mismo tiempo
```

---

### 4. **registerMaterialsUsed** - Registrar Materiales Utilizados (RF-INS-07)
**Ubicación:** `OfficeInstallationCoordinationController::registerMaterialsUsed()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Registrar materiales válidos | Materiales + cantidades correctas | Registrados | CRÍTICO |
| 2 | Cantidad excede stock | cantidad > stock_actual | HTTP 422, "Sin stock" | CRÍTICO |
| 3 | Material inexistente | material_id = 99999 | HTTP 422, validación | ALTA |
| 4 | Cantidad negativa | cantidad = -10 | HTTP 422, validación | ALTA |
| 5 | Cantidad 0 | cantidad = 0 | HTTP 422 o permitir | MEDIA |
| 6 | Stock se descuenta | Materiales registrados | Stock actualizado | CRÍTICO |
| 7 | Movimiento en Kardex | Materiales usados | Registro CONSUMO creado | CRÍTICO |
| 8 | Registrar 2 veces mismo material | material_id duplicado | Sumar cantidades | MEDIA |
| 9 | Registrar sin instalación iniciada | Estado: PENDIENTE | HTTP 422, "Iniciar primero" | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$material1 = Material::factory()->create(['stock_actual' => 100]);
$material2 = Material::factory()->create(['stock_actual' => 50]);

$order = Installation::factory()->create(['estado' => 'EN_PROGRESO']);

$this->post("/installations/{$order->id}/materials", [
    'materials' => [
        ['material_id' => $material1->id, 'cantidad' => 20],
        ['material_id' => $material2->id, 'cantidad' => 10]
    ]
]);

// Verificar:
// - material1 stock = 80
// - material2 stock = 40
// - Kardex tiene 2 movimientos tipo CONSUMO

// CASO CRÍTICO: Sin stock suficiente
$material = Material::factory()->create(['stock_actual' => 5]);
$this->post("/installations/{$order->id}/materials", [
    'materials' => [
        ['material_id' => $material->id, 'cantidad' => 10]  // Excede stock
    ]
]);
// Debe rechazar

// CASO EDGE: Usar exactamente todo el stock
$material = Material::factory()->create(['stock_actual' => 50]);
$this->post("/installations/{$order->id}/materials", [
    'materials' => [
        ['material_id' => $material->id, 'cantidad' => 50]  // stock = 0
    ]
]);
// Permitir
```

---

## 📝 Comandos de Ejecución

```bash
# Pedidos
php artisan test testsphpunit/Instalaciones/InstallationOrderTest.php

# Ejecución
php artisan test testsphpunit/Instalaciones/InstallationExecutionTest.php

# Todos
php artisan test testsphpunit/Instalaciones/
```

---

## 🎯 Objetivos de Cobertura

- **Pedidos:** 95%+
- **Ejecución:** 95%+
- **Upload de archivos:** 100%
- **Validación de estados:** 100%
- **Integración Contratos-Pagos:** 100%

---

## 📊 Prioridades

1. **CRÍTICO:** completeInstallation (genera pagos), startInstallation (descuenta stock), uploadPhoto (seguridad)
2. **ALTA:** store (validaciones), assignTechnician, registerMaterialsUsed
3. **MEDIA:** reschedule, notificaciones, performance
