# 💥 PLAN DE DESTRUCCIÓN - MÓDULO PAGOS

**Objetivo:** Torturar el sistema de pagos con casos extremos que rompan la lógica de negocio crítica.

---

## 📊 Resumen del Módulo

| Métodos Implementados | Métodos Faltantes | Cobertura |
|----------------------|-------------------|-----------|
| ✅ 31/33 | ❌ 2/33 | 93.9% |

**Estado:** ⚠️ FALTAN 2 MÉTODOS DE BAJA PRIORIDAD (createReminder, manageReactivation)

---

## 🎯 Métodos Críticos a Torturar

### 1. **generatePayments** - Generar Pagos Automáticos (RF-PAG-01)
**Ubicación:** `PaymentController::generatePayments()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Generar pagos contrato 12 meses | Contrato activo 12 meses | 12 pagos creados | CRÍTICO |
| 2 | Generar pagos contrato 1 mes | Contrato 1 mes | 1 pago creado | ALTA |
| 3 | Generar pagos contrato 0 meses | duracion_meses = 0 | HTTP 422 o 0 pagos | ALTA |
| 4 | Generar pagos contrato negativo | duracion_meses = -5 | HTTP 422, error | ALTA |
| 5 | Generar pagos fecha pasada | fecha_inicio = 2020-01-01 | Pagos con fechas pasadas | MEDIA |
| 6 | Generar pagos duplicados | Llamar 2 veces generatePayments | Solo 1 set de pagos | CRÍTICO |
| 7 | Generar pagos sin plan asociado | Plan eliminado | HTTP 422, integridad | ALTA |
| 8 | Generar pagos con monto 0 | Plan con precio = 0 | Pagos con monto 0 o error | MEDIA |
| 9 | Generar pagos con monto negativo | Plan con precio = -50 | HTTP 422, validación | ALTA |
| 10 | Fecha vencimiento correcta | Pagos generados | Vence día 5 de cada mes | CRÍTICO |
| 11 | Código de pago único | 100 pagos generados | Todos códigos únicos | CRÍTICO |
| 12 | Estado inicial PENDIENTE | Pagos generados | Todos estado = PENDIENTE | CRÍTICO |
| 13 | Generar pagos extremos | duracion_meses = 999 | 999 pagos o límite máximo | BAJA |
| 14 | Generar pagos concurrente | 2 requests simultáneos | Sin duplicados | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO: Contrato 12 meses
$contract = Contract::factory()->create([
    'fecha_inicio' => '2026-10-15',
    'duracion_meses' => 12,
    'plan_id' => Plan::factory()->create(['precio' => 100.00])
]);
// Espera: 12 pagos de S/ 100 c/u, vencen día 5 de cada mes

// CASO EDGE: Contrato inicia día 31
$contract = Contract::factory()->create([
    'fecha_inicio' => '2026-01-31',  // Enero 31
    'duracion_meses' => 3
]);
// Pago 1: Vence 2026-02-05
// Pago 2: Vence 2026-03-05
// Pago 3: Vence 2026-04-05

// CASO EXTREMO: Doble generación (race condition)
DB::transaction(function() use ($contract) {
    $payments1 = PaymentController::generatePayments($contract);
    $payments2 = PaymentController::generatePayments($contract);
    // Solo debe crear 1 set de pagos
});

// CASO MALICIOSO: Modificar monto durante generación
$plan = Plan::factory()->create(['precio' => 100.00]);
$contract = Contract::factory()->create(['plan_id' => $plan->id]);
// Thread 1: generatePayments()
// Thread 2: $plan->update(['precio' => 50.00])
// ¿Qué monto tienen los pagos?

// CASO EDGE: Código de pago duplicado
// Forzar que 2 pagos generen el mismo código
Payment::factory()->create(['codigo' => 'PAG-2026-0001']);
$contract = Contract::factory()->create();
// ¿generatePayments() detecta el duplicado?

// CASO EXTREMO: Plan con precio decimal largo
$plan = Plan::factory()->create(['precio' => 99.99999999]);
```

---

### 2. **confirmPayment** - Confirmar Pago Realizado (RF-PAG-16)
**Ubicación:** `PaymentController::confirmPayment()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Confirmar pago PENDIENTE | Pago válido PENDIENTE | Estado → REALIZADO | CRÍTICO |
| 2 | Confirmar pago VENCIDO | Pago VENCIDO + datos | Estado → REALIZADO | CRÍTICO |
| 3 | Confirmar pago IMPAGO | Pago IMPAGO (+5 días) | Estado → REALIZADO + reactivar contrato | CRÍTICO |
| 4 | Confirmar pago ya REALIZADO | Estado: REALIZADO | HTTP 422, "Ya confirmado" | CRÍTICO |
| 5 | Confirmar pago ANULADO | Estado: ANULADO | HTTP 422, "No se puede confirmar" | ALTA |
| 6 | Confirmar sin método de pago | metodo_pago = null | HTTP 422, campo requerido | ALTA |
| 7 | Confirmar sin número operación | numero_operacion = null | HTTP 422, campo requerido | ALTA |
| 8 | Confirmar sin fecha pago | fecha_pago = null | HTTP 422, campo requerido | ALTA |
| 9 | Confirmar fecha pago futura | fecha_pago = mañana | HTTP 422, "Fecha inválida" | ALTA |
| 10 | Confirmar fecha pago muy pasada | fecha_pago = 2020-01-01 | HTTP 422 o warning | MEDIA |
| 11 | Confirmar con monto diferente | monto diferente al esperado | HTTP 422 o ajuste | MEDIA |
| 12 | Confirmar genera constancia PDF | Pago confirmado | PDF creado y asociado | CRÍTICO |
| 13 | Confirmar registra responsable | Usuario autenticado | responsable_id guardado | ALTA |
| 14 | Confirmar actualiza fecha_pago | Fecha ingresada | fecha_pago actualizada | ALTA |
| 15 | Usuario sin permisos | Usuario cliente | HTTP 403, Forbidden | ALTA |
| 16 | Número operación duplicado | Mismo numero_operacion | ¿Permitir o rechazar? | MEDIA |
| 17 | SQL injection en numero_operacion | "'; DROP TABLE--" | Datos escapados | CRÍTICO |
| 18 | XSS en observaciones | "<script>alert('xss')</script>" | Datos sanitizados | ALTA |
| 19 | Confirmar pago de contrato cancelado | Contrato CANCELADO | ¿Permitir o rechazar? | MEDIA |
| 20 | Confirmación concurrente | 2 usuarios confirman mismo pago | Solo 1 éxito | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$payment = Payment::factory()->create([
    'estado' => 'PENDIENTE',
    'monto' => 100.00,
    'fecha_vencimiento' => now()->addDays(5)
]);
$response = $this->post("/payments/{$payment->id}/confirm", [
    'metodo_pago' => 'TRANSFERENCIA',
    'numero_operacion' => '000123456789',
    'fecha_pago' => now()->format('Y-m-d'),
    'observaciones' => 'Pago realizado'
]);

// CASO CRÍTICO: Confirmar pago IMPAGO reactiva contrato
$contract = Contract::factory()->create(['estado' => 'CANCELADO']);
$payment = Payment::factory()->create([
    'contract_id' => $contract->id,
    'estado' => 'IMPAGO',
    'fecha_vencimiento' => now()->subDays(6),
    'monto' => 100.00
]);
// Al confirmar, ¿el contrato pasa a ACTIVO automáticamente?

// CASO EDGE: Doble confirmación (race condition)
$payment = Payment::factory()->create(['estado' => 'PENDIENTE']);
// Thread 1: POST /payments/{id}/confirm
// Thread 2: POST /payments/{id}/confirm (al mismo tiempo)
// Solo debe procesarse 1

// CASO MALICIOSO: SQL Injection
$this->post("/payments/{$payment->id}/confirm", [
    'numero_operacion' => "'; DROP TABLE payments;--",
    'observaciones' => "'; DELETE FROM contracts WHERE 1=1;--"
]);

// CASO EXTREMO: Monto muy diferente
$payment = Payment::factory()->create(['monto' => 100.00]);
$this->post("/payments/{$payment->id}/confirm", [
    'monto_pagado' => 50.00  // Pago parcial
]);
$this->post("/payments/{$payment->id}/confirm", [
    'monto_pagado' => 200.00  // Pago excesivo
]);

// CASO EDGE: Método de pago inválido
$this->post("/payments/{$payment->id}/confirm", [
    'metodo_pago' => 'BITCOIN',  // No válido
    'metodo_pago' => 'CASH_APP',
    'metodo_pago' => '<script>alert()</script>'
]);

// CASO EXTREMO: Número operación muy largo
$this->post("/payments/{$payment->id}/confirm", [
    'numero_operacion' => str_repeat('9', 1000)
]);

// CASO EDGE: Confirmar todos los pagos de un contrato al mismo tiempo
$contract = Contract::factory()->create();
$payments = Payment::factory()->count(12)->create([
    'contract_id' => $contract->id,
    'estado' => 'PENDIENTE'
]);
// Confirmar los 12 pagos simultáneamente
```

---

### 3. **annul** - Anular Pago (RF-PAG-22)
**Ubicación:** `PaymentController::annul()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Anular pago PENDIENTE | Pago PENDIENTE válido | Estado → ANULADO | ALTA |
| 2 | Anular pago VENCIDO | Pago VENCIDO | Estado → ANULADO | ALTA |
| 3 | Anular pago REALIZADO | Pago REALIZADO | HTTP 422, "No se puede anular" | CRÍTICO |
| 4 | Anular pago ya ANULADO | Estado: ANULADO | HTTP 422, "Ya anulado" | ALTA |
| 5 | Anular pago IMPAGO | Pago IMPAGO | Estado → ANULADO | ALTA |
| 6 | Anular sin motivo | motivo = null | HTTP 422, campo requerido | ALTA |
| 7 | Anular con motivo SQL injection | motivo = "'; DROP--" | Datos escapados | ALTA |
| 8 | Anular pago afecta contrato | Último pago anulado | ¿Contrato cambia estado? | MEDIA |
| 9 | Anular registra responsable | Usuario autenticado | responsable_anulacion_id | ALTA |
| 10 | Usuario sin permisos | Usuario vendedor | HTTP 403, Forbidden | ALTA |
| 11 | Anular pago de contrato activo | Contrato ACTIVO + pago PENDIENTE | Anulación exitosa | MEDIA |
| 12 | Anular todos los pagos de contrato | Anular 12 pagos | Todos → ANULADO | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$payment = Payment::factory()->create(['estado' => 'PENDIENTE']);
$response = $this->post("/payments/{$payment->id}/annul", [
    'motivo' => 'Error en el registro'
]);

// CASO CRÍTICO: No se puede anular pago REALIZADO
$payment = Payment::factory()->create([
    'estado' => 'REALIZADO',
    'fecha_pago' => now()->subDays(5)
]);
$response = $this->post("/payments/{$payment->id}/annul", [
    'motivo' => 'Intentar anular'
]);
// Debe retornar HTTP 422

// CASO EDGE: Anular el único pago PENDIENTE de un contrato
$contract = Contract::factory()->create(['estado' => 'ACTIVO']);
$payment = Payment::factory()->create([
    'contract_id' => $contract->id,
    'estado' => 'PENDIENTE'
]);
// ¿Al anular, el contrato debe cambiar de estado?

// CASO MALICIOSO
$this->post("/payments/{$payment->id}/annul", [
    'motivo' => "'; DROP TABLE payments; --",
    'estado' => 'REALIZADO'  // Intentar mass assignment
]);
```

---

### 4. **generateReceipt** - Generar Constancia PDF (RF-PAG-19)
**Ubicación:** `PaymentController::generateReceipt()`

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Generar recibo pago REALIZADO | Pago confirmado | PDF creado | CRÍTICO |
| 2 | Generar recibo pago PENDIENTE | Pago PENDIENTE | HTTP 422, "Pagar primero" | ALTA |
| 3 | Generar recibo pago ANULADO | Pago ANULADO | HTTP 422, "Pago anulado" | ALTA |
| 4 | Generar recibo múltiples veces | Mismo pago | Mismo PDF o nuevo | MEDIA |
| 5 | PDF contiene datos correctos | Pago confirmado | Monto, fecha, cliente correctos | CRÍTICO |
| 6 | PDF con caracteres especiales | Cliente "José María Ñoño & Co." | PDF renderiza correctamente | ALTA |
| 7 | Generar recibo sin contrato | Contrato eliminado | HTTP 422, integridad | MEDIA |
| 8 | Descargar recibo inexistente | PDF no existe | Generar on-demand | MEDIA |
| 9 | Usuario sin permisos | Usuario cliente | HTTP 403 o permitir | MEDIA |
| 10 | Generación masiva concurrente | 100 PDFs al mismo tiempo | Todos exitosos | BAJA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$payment = Payment::factory()->create([
    'estado' => 'REALIZADO',
    'monto' => 100.00,
    'numero_operacion' => '000123456789',
    'fecha_pago' => now()
]);
$response = $this->get("/payments/{$payment->id}/receipt");
// Debe retornar PDF con Content-Type: application/pdf

// CASO EDGE: Cliente con caracteres especiales
$client = Client::factory()->create([
    'nombre_completo' => "José María Ñoño & Co. <S.A.C.>"
]);
$contract = Contract::factory()->create(['client_id' => $client->id]);
$payment = Payment::factory()->create([
    'contract_id' => $contract->id,
    'estado' => 'REALIZADO'
]);
// PDF debe escapar caracteres HTML/especiales

// CASO EXTREMO: Generar 1000 recibos seguidos
$payments = Payment::factory()->count(1000)->create(['estado' => 'REALIZADO']);
foreach($payments as $payment) {
    $this->get("/payments/{$payment->id}/receipt");
}
// ¿Performance adecuado?
```

---

### 5. **markAsOverdue** - Cambiar a VENCIDO (RF-PAG-15)
**Ubicación:** `UpdateOverduePayments` (Comando)

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Pago vence hoy | fecha_vencimiento = hoy | Estado → VENCIDO | CRÍTICO |
| 2 | Pago vence mañana | fecha_vencimiento = mañana | Estado = PENDIENTE | CRÍTICO |
| 3 | Pago vencido hace 1 día | fecha_vencimiento = ayer | Estado → VENCIDO | CRÍTICO |
| 4 | Pago vencido hace 4 días (gracia) | fecha_vencimiento = -4 días | Estado = VENCIDO (no IMPAGO) | CRÍTICO |
| 5 | Pago vencido hace 5 días | fecha_vencimiento = -5 días | Estado → IMPAGO | CRÍTICO |
| 6 | Pago ya REALIZADO no cambia | Estado: REALIZADO + vencido | Estado = REALIZADO | ALTA |
| 7 | Pago ANULADO no cambia | Estado: ANULADO + vencido | Estado = ANULADO | ALTA |
| 8 | Comando se ejecuta diariamente | Ejecutar 2 veces mismo día | Mismo resultado | ALTA |
| 9 | Actualización masiva | 1000 pagos vencidos | Todos actualizados | ALTA |
| 10 | Cambio de estado envía email | Pago → VENCIDO | Email al cliente | MEDIA |

#### 📋 Diccionario de Datos:

```php
// CASO CRÍTICO: Periodo de gracia 4 días
Payment::factory()->create([
    'estado' => 'PENDIENTE',
    'fecha_vencimiento' => now()->subDays(4)->startOfDay()
]);
// Ejecutar comando: php artisan payments:update-overdue
// Estado debe ser VENCIDO (no IMPAGO todavía)

// CASO CRÍTICO: Día 5 pasa a IMPAGO
Payment::factory()->create([
    'estado' => 'VENCIDO',
    'fecha_vencimiento' => now()->subDays(5)->startOfDay()
]);
// Ejecutar comando
// Estado debe cambiar a IMPAGO

// CASO EDGE: Exactamente medianoche
Payment::factory()->create([
    'fecha_vencimiento' => now()->startOfDay()  // 00:00:00
]);
// ¿Es vencido hoy o mañana?

// CASO EXTREMO: Múltiples ejecuciones del comando
Artisan::call('payments:update-overdue');
Artisan::call('payments:update-overdue');
Artisan::call('payments:update-overdue');
// Debe ser idempotente (mismo resultado)
```

---

### 6. **sendPreDueReminder** - Enviar Recordatorio Previo (RF-PAG-26)
**Ubicación:** `SendPaymentReminders` (Comando)

#### 🔥 Casos de Tortura:

| # | Caso de Prueba | Datos de Entrada | Resultado Esperado | Prioridad |
|---|----------------|------------------|-------------------|-----------|
| 1 | Pago vence en 2 días | fecha_vencimiento = +2 días | Email enviado | CRÍTICO |
| 2 | Pago vence en 1 día | fecha_vencimiento = mañana | Email enviado | ALTA |
| 3 | Pago vence en 3 días | fecha_vencimiento = +3 días | Sin email | ALTA |
| 4 | Pago ya vencido | fecha_vencimiento = ayer | Sin email | ALTA |
| 5 | Pago ya REALIZADO | Estado: REALIZADO + vence pronto | Sin email | ALTA |
| 6 | Pago ANULADO | Estado: ANULADO + vence pronto | Sin email | ALTA |
| 7 | Cliente sin email | email = null | Log error, no enviar | ALTA |
| 8 | Email inválido | email = "invalid" | Log error, no enviar | MEDIA |
| 9 | Envío masivo | 1000 recordatorios | Todos enviados | MEDIA |
| 10 | Email duplicado mismo día | Ejecutar 2 veces | Solo 1 email por pago | ALTA |

#### 📋 Diccionario de Datos:

```php
// CASO VÁLIDO
$client = Client::factory()->create(['email' => 'test@example.com']);
$contract = Contract::factory()->create(['client_id' => $client->id]);
$payment = Payment::factory()->create([
    'contract_id' => $contract->id,
    'estado' => 'PENDIENTE',
    'fecha_vencimiento' => now()->addDays(2)
]);
// Ejecutar: php artisan payments:send-reminders
// Email debe enviarse a test@example.com

// CASO EDGE: Cliente sin email
$client = Client::factory()->create(['email' => null]);
// ¿El comando falla o solo loguea?

// CASO EXTREMO: Múltiples pagos mismo cliente
$client = Client::factory()->create();
$contract = Contract::factory()->create(['client_id' => $client->id]);
Payment::factory()->count(5)->create([
    'contract_id' => $contract->id,
    'fecha_vencimiento' => now()->addDays(2)
]);
// ¿1 email con 5 pagos o 5 emails separados?
```

---

## 🚨 Métodos Faltantes

| ID | Método | Requerimiento | Impacto | Estado |
|---|---|---|---|---|
| RF-PAG-21 | **preventModification** | Restringir modificación de pago realizado | 🔴 CRÍTICO | ✅ IMPLEMENTADO (`canBeEdited()`) |
| RF-PAG-23 | **applyGracePeriod** | Aplicar periodo de tolerancia antes de IMPAGO | 🔴 CRÍTICO | ✅ IMPLEMENTADO (4 días en comando) |
| RF-PAG-25 | **notifyContractCancellation** | Notificar cancelación contractual | 🟡 ALTA | ✅ IMPLEMENTADO (`SendPaymentReminders`) |
| RF-PAG-28 | **sendCancellationNotice** | Enviar aviso de cancelación | 🟡 ALTA | ✅ IMPLEMENTADO (`ContractCancellationNotice`) |
| RF-PAG-29 | **createReminder** | Crear recordatorios internos | 🟢 MEDIA | ❌ NO EXISTE |
| RF-PAG-32 | **manageReactivation** | Gestión de reactivación post-regularización | 🟡 ALTA | ❌ NO EXISTE |

### ✅ Métodos IMPLEMENTADOS:

#### **preventModification** (RF-PAG-21) - ✅ EXISTE
```php
// Implementado en Payment::canBeEdited() y PaymentService::update()
public function canBeEdited(): bool
{
    // Solo se pueden editar pagos PENDIENTES o VENCIDOS
    return in_array($this->estado, ['PENDIENTE', 'VENCIDO'], true);
}

// En PaymentService::update()
if (!$payment->canBeEdited()) {
    throw ValidationException::withMessages([
        'estado' => 'No se puede editar un pago con estado: ' . $payment->estado
    ]);
}
```

#### **applyGracePeriod** (RF-PAG-23) - ✅ EXISTE
```php
// Implementado en UpdateOverduePayments command
// 4 DÍAS de gracia entre VENCIDO → IMPAGO
protected $description = 'Actualiza automáticamente los estados de pagos: 
    PENDIENTE → VENCIDO (al pasar fecha) 
    y VENCIDO → IMPAGO (4 días después)';

$toleranceDays = 4;  // Periodo de gracia
$cutoffDate = $today->copy()->subDays($toleranceDays);

$overdueToCancel = Payment::where('estado', 'VENCIDO')
    ->where('fecha_vencimiento', '<', $cutoffDate)
    ->get();
```

#### **notifyContractCancellation + sendCancellationNotice** (RF-PAG-25, RF-PAG-28) - ✅ EXISTEN
```php
// Implementado en SendPaymentReminders command
// Busca contratos cancelados que requieren notificación
$cancelledContracts = Contract::where('estado', 'CANCELADO')
    ->where('fecha_cancelacion', '>=', now()->subDays(7))
    ->whereHas('payments', function($q) {
        $q->where('estado', 'ANULADO')
          ->whereNull('notificacion_anulacion_enviada_en');
    })->get();

// Envía email ContractCancellationNotice
Mail::to($client->email)->send(
    new ContractCancellationNotice($contract, $pagoAnulado)
);
$pagoAnulado->markCancellationNotificationSent();
```

### ❌ Métodos FALTANTES (2):

#### **createReminder** (RF-PAG-29)
```php
// NO EXISTE - Crear recordatorios internos en el sistema
// Los recordatorios se envían por email, pero no hay gestión interna
public function createReminder(Payment $payment, string $type): Reminder
{
    return Reminder::create([
        'payment_id' => $payment->id,
        'type' => $type, // 'pre_due', 'overdue', 'cancelled'
        'scheduled_for' => $this->calculateReminderDate($payment, $type),
        'status' => 'PENDING'
    ]);
}
```

#### **manageReactivation** (RF-PAG-32)
```php
// NO EXISTE - Gestión específica de reactivación post-regularización
// La reactivación existe en ContractController, pero falta gestión de pagos
public function manageReactivation(Contract $contract): void
{
    // Verificar que todos los pagos IMPAGO estén saldados
    $unpaidCount = $contract->payments()
        ->where('estado', 'IMPAGO')
        ->count();
    
    if ($unpaidCount > 0) {
        throw new Exception('Debe saldar todos los pagos impagos antes de reactivar');
    }
    
    // Lógica adicional de reactivación
}
```

---

## 📝 Comandos de Ejecución

```bash
# Ejecutar todos los tests de Pagos
php artisan test testsphpunit/Pagos/PaymentTest.php

# Ejecutar test específico
php artisan test testsphpunit/Pagos/PaymentTest.php --filter=test_confirmar_pago_realizado

# Ejecutar comandos automáticos
php artisan payments:update-overdue
php artisan payments:send-reminders
php artisan contracts:cancel-for-non-payment
```

---

## 🎯 Objetivos de Cobertura

- **Líneas de código:** 90%+
- **Métodos críticos (generar, confirmar, anular):** 100%
- **Comandos automáticos:** 100%
- **Edge cases de fechas:** 100%
- **Validaciones de estado:** 100%

---

## 📊 Prioridades de Implementación

1. **CRÍTICO:** confirmPayment, generatePayments, markAsOverdue (gracia 4 días)
2. **ALTA:** annul, generateReceipt, validaciones de estado
3. **MEDIA:** Emails, concurrencia, métodos faltantes
