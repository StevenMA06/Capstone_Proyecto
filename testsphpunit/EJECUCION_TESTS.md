# Ejecución de Tests PHPUnit - Estado Actual

**Fecha**: 2026-10-13  
**Objetivo**: Verificar que los tests de Contratos y Pagos se ejecuten correctamente

---

## ✅ Problemas Resueltos

### 1. **Migración con permisos faltantes**
- **Error**: `Undefined array key "orders.intake.create"`
- **Solución**: Agregado validación `isset()` en migración antes de acceder al array
- **Archivo**: `database/migrations/2026_10_12_000002_add_installation_intake_permissions.php`

### 2. **Modelos sin trait HasFactory**
- **Error**: `Call to undefined method App\Models\Client::factory()`
- **Solución**: Agregado `use HasFactory;` en:
  - `app/Models/Client.php`
  - `app/Models/Employee.php`
  - `app/Models/Contract.php`
  - `app/Models/Payment.php`
  - `app/Models/CoverageZone.php`
  - `app/Models/ServicePlan.php`

### 3. **Factories faltantes**
- **Creadas**:
  - `database/factories/EmployeeFactory.php`
  - `database/factories/CoverageZone Factory.php`

### 4. **Campo creado_por faltante en ClientFactory**
- **Error**: `Field 'creado_por' doesn't have a default value`
- **Solución**: Agregado `'creado_por' => 1` en `ClientFactory.php`

### 5. **Uso de Plan vs ServicePlan**
- **Problema**: Los tests usaban `Plan` pero el modelo es `ServicePlan`
- **Solución**: Actualizado imports y referencias en tests

---

## 🔄 En Progreso

Ejecutando test: `test_store_crea_contrato_con_datos_validos`

### Próximos pasos:
1. ✅ Arreglar factories y modelos
2. ⏳ Ejecutar test completo de Contratos
3. ⏳ Ejecutar test completo de Pagos
4. ⏳ Documentar resultados esperados vs. reales

---

## 📊 Cobertura de Tests Planificada

### Contratos (17 tests)
- `store()` - 6 tests (datos válidos, cliente inexistente, SQL injection, XSS, duración negativa/cero)
- `activate()` - 3 tests (cambio de estado, rechazo si ya activo, rechazo si cancelado)
- `reactivate()` - 2 tests (con deuda saldada, sin saldar deuda)
- `cancelForNonPayment()` - 3 tests (5 días, 4 días gracia, sin deuda)
- `update()` - 3 tests (cambiar dirección, rechazar cancelado, mass assignment)

### Pagos (24 tests)
- `generatePayments()` - 5 tests (12 pagos, códigos únicos, estado PENDIENTE, día 5, no duplicados)
- `confirmPayment()` - 5 tests (cambio a REALIZADO, rechazo duplicado, SQL injection, XSS, generar PDF)
- `markAsOverdue()` - 5 tests (PENDIENTE→VENCIDO, gracia 4 días, IMPAGO día 5, no afecta REALIZADO/ANULADO)
- `annul()` - 4 tests (PENDIENTE→ANULADO, rechazar REALIZADO, rechazar sin motivo, SQL injection)
- `preventModification()` - 3 tests (rechazar REALIZADO, permitir PENDIENTE, permitir VENCIDO)

**Total**: 41 tests exhaustivos

---

## 🎯 Tests Destructivos Implementados

### SQL Injection
- ✅ `'; DROP TABLE contratos;--` en dirección de instalación
- ✅ `'; DROP TABLE pagos;--` en número de operación de pago
- ✅ `'; DELETE FROM contratos WHERE 1=1;--` en referencias

### XSS
- ✅ `<script>alert("XSS")</script>` en referencia de contrato
- ✅ `<script>alert("XSS")</script>` en observaciones de pago

### Mass Assignment
- ✅ Intento de cambiar `codigo` directamente
- ✅ Intento de cambiar `estado` sin flujo correcto

### Race Conditions
- ⏳ Pendiente: Tests de concurrencia en pagos
- ⏳ Pendiente: Tests de concurrencia en equipos (lockForUpdate)

---

## 📝 Notas

- Se usa `RefreshDatabase` para limpiar BD entre tests
- Base de datos de tests: `Db_LunnysPeru_test`
- Los tests están en `testsphpunit/` separados de `tests/` estándar
- PHPUnit 11.5.56 (deprecation warnings de `/** @test */` ignorados - migrar a attributes después)

---

**Última actualización**: 2026-10-13 12:50:00
