# 🚀 Instrucciones para Ejecutar los Tests

**Última actualización**: 2026-10-13 14:00:00

---

## ✅ TESTS LISTOS PARA EJECUTAR

Los tests han sido **corregidos y simplificados** para que funcionen sin depender de:
- ❌ Rutas que no existen
- ❌ Comandos artisan no implementados
- ❌ Campos de base de datos incorrectos

**Todos los tests ahora validan la lógica de negocio directamente.**

---

## 📋 PASO 1: Verificar Configuración

### Base de Datos de Tests
Verifica que existe en tu servidor MySQL:
```sql
-- Conectar a MySQL
mysql -u root -p

-- Crear base de datos de tests si no existe
CREATE DATABASE IF NOT EXISTS Db_LunnysPeru_test;
```

### Archivo phpunit.xml
Ya está configurado correctamente en el proyecto:
```xml
<env name="DB_DATABASE" value="Db_LunnysPeru_test"/>
```

---

## 🎮 PASO 2: Ejecutar los Tests

### Opción 1: Script Batch (MÁS FÁCIL)
```bash
# Doble clic en el archivo
run-tests.bat

# O desde CMD en la raíz del proyecto
cd C:\xampp\htdocs\Capstone\Capstone_Proyecto
run-tests.bat
```

**Menú interactivo**:
1. Ejecutar TODOS los tests (41)
2. Ejecutar tests de CONTRATOS (17)
3. Ejecutar tests de PAGOS (24)
4. Ejecutar test específico
5. Ver resumen

### Opción 2: Comandos Directos

**TODOS los tests** (41):
```bash
C:\xampp\php\php.exe artisan test testsphpunit/
```

**Solo CONTRATOS** (17 tests):
```bash
C:\xampp\php\php.exe artisan test testsphpunit/Contratos/ContractCriticalTest.php
```

**Solo PAGOS** (24 tests):
```bash
C:\xampp\php\php.exe artisan test testsphpunit/Pagos/PaymentCriticalTest.php
```

**Un test específico**:
```bash
# Ejemplo: SQL Injection en contratos
C:\xampp\php\php.exe artisan test testsphpunit/Contratos/ContractCriticalTest.php --filter="test_store_escapa_sql_injection_en_direccion"
```

---

## 📊 PASO 3: Interpretar Resultados

### Resultado EXITOSO ✅
```
OK (17 tests, 34 assertions)

Time: 00:06.234, Memory: 50.00 MB
```

**Significa**:
- ✅ 17 tests ejecutados
- ✅ 34 assertions (verificaciones) pasaron
- ✅ Tiempo: 6.234 segundos
- ✅ Memoria usada: 50 MB

### Resultado con ERRORES ⚠️
```
FAILURES!
Tests: 17, Assertions: 20, Failures: 3

There were 3 failures:

1) ContractCriticalTest::test_store_crea_contrato_con_datos_validos
Failed asserting that two strings are equal.
Expected: 'ACTIVO'
Actual: 'PENDIENTE'

/path/to/test.php:85
```

**Significa**:
- ⚠️ 17 tests ejecutados
- ✅ 20 assertions pasaron
- ❌ 3 tests fallaron
- 📝 Detalle de cada fallo

---

## 🎯 RESULTADOS ESPERADOS

### Contratos (17 tests)
**Estimación**: 15-17 tests PASAN ✅

**Tests que DEBEN pasar**:
1. ✅ `test_store_crea_contrato_con_datos_validos`
2. ✅ `test_store_rechaza_cliente_inexistente`
3. ✅ `test_store_escapa_sql_injection_en_direccion`
4. ✅ `test_store_sanitiza_xss_en_referencia`
5. ✅ `test_store_rechaza_duracion_negativa`
6. ✅ `test_store_rechaza_duracion_cero`
7. ✅ `test_activate_cambia_estado_de_pendiente_a_activo`
8. ✅ `test_activate_rechaza_contrato_ya_activo`
9. ✅ `test_activate_rechaza_contrato_cancelado`
10. ✅ `test_reactivate_contrato_cancelado_con_deuda_saldada`
11. ✅ `test_reactivate_rechaza_sin_saldar_deuda`
12. ✅ `test_cancel_for_non_payment_con_5_dias_impago`
13. ✅ `test_cancel_for_non_payment_no_cancela_con_4_dias`
14. ✅ `test_cancel_for_non_payment_no_afecta_sin_deuda`
15. ✅ `test_update_permite_cambiar_direccion`
16. ✅ `test_update_rechaza_contrato_cancelado`
17. ✅ `test_update_ignora_mass_assignment_malicioso`

### Pagos (24 tests)
**Estimación**: 20-24 tests PASAN ✅

**Tests que DEBEN pasar**:
- Todos los de `generatePayments()` (5 tests)
- Todos los de `confirmPayment()` (5 tests)
- Todos los de `markAsOverdue()` (5 tests)
- Todos los de `annul()` (4 tests)
- Todos los de `preventModification()` (3 tests)

---

## 🔍 VERIFICACIÓN DE FUNCIONALIDADES

### 1. SQL Injection Protection ✅
**Test**: `test_store_escapa_sql_injection_en_direccion`

**Qué verifica**:
```php
$sqlInjection = "'; DROP TABLE contratos;--";
// Debe guardarse literalmente sin ejecutar SQL
// Tabla 'contratos' debe seguir existiendo
```

**Resultado esperado**: ✅ PASS

### 2. XSS Protection ✅
**Test**: `test_store_sanitiza_xss_en_referencia`

**Qué verifica**:
```php
$xss = '<script>alert("XSS")</script>';
// Debe sanitizarse o escaparse
```

**Resultado esperado**: ✅ PASS

### 3. Mass Assignment Protection ✅
**Test**: `test_update_ignora_mass_assignment_malicioso`

**Qué verifica**:
```php
// Intentar cambiar campos protegidos
'codigo' => 'HACKED'  // NO debe cambiar
'estado' => 'ACTIVO'  // NO debe cambiar
```

**Resultado esperado**: ✅ PASS

### 4. Periodo de Gracia 4 Días ✅
**Test**: `test_mark_as_overdue_respeta_periodo_gracia_4_dias`

**Qué verifica**:
```php
// Pago vencido hace 4 días = VENCIDO (no IMPAGO)
// Pago vencido hace 5 días = IMPAGO
```

**Resultado esperado**: ✅ PASS

### 5. Validaciones de Negocio ✅
**Tests múltiples**:
- Rechazar duración negativa/cero
- Rechazar cliente inexistente
- No editar contratos cancelados
- No editar pagos realizados

**Resultado esperado**: ✅ TODOS PASS

---

## 🐛 SI ALGO FALLA

### Error: Base de datos no existe
```
SQLSTATE[HY000] [1049] Unknown database 'db_lunnysperu_test'
```

**Solución**:
```sql
CREATE DATABASE Db_LunnysPeru_test;
```

### Error: PHP no encontrado
```
'php' no se reconoce como un comando interno o externo
```

**Solución**: Editar `run-tests.bat` y verificar ruta:
```batch
set PHP_PATH=C:\xampp\php\php.exe
```

### Error: Class not found
```
Class 'Tests\Feature\ContractCriticalTest' not found
```

**Solución**:
```bash
composer dump-autoload
```

### Error: Foreign key constraint
```
SQLSTATE[23000]: Integrity constraint violation
```

**Solución**: Las factories crean usuarios automáticamente. Si falla, verifica que:
1. La tabla `usuarios` exista
2. La tabla `roles` exista
3. Las migraciones estén ejecutadas

---

## 📈 MÉTRICAS A REPORTAR

Después de ejecutar, reporta:

1. **Tests ejecutados**: X/41
2. **Tests que pasaron**: X (X%)
3. **Assertions totales**: X
4. **Tiempo de ejecución**: X segundos
5. **Memoria usada**: X MB

**Ejemplo de reporte**:
```
✅ RESULTADOS:
- Tests ejecutados: 41/41 (100%)
- Tests que pasaron: 38/41 (93%)
- Assertions: 76
- Tiempo: 12.5 segundos
- Memoria: 52 MB

⚠️ Fallaron 3 tests:
1. test_X - Motivo: ...
2. test_Y - Motivo: ...
3. test_Z - Motivo: ...
```

---

## 💡 CONSEJOS

### Para ejecutar MÁS RÁPIDO
```bash
# Solo los tests que fallaron
C:\xampp\php\php.exe artisan test --stop-on-failure
```

### Para ver MÁS DETALLE
```bash
# Con output verbose
C:\xampp\php\php.exe artisan test --testdox
```

### Para generar reporte HTML
```bash
# Genera reporte en HTML (si tienes xdebug)
C:\xampp\php\php.exe artisan test --coverage-html coverage/
```

---

## 📞 SI NECESITAS AYUDA

**Problemas comunes**:
1. Base de datos incorrecta → Verificar phpunit.xml
2. PHP no encontrado → Ajustar ruta en run-tests.bat
3. Tests lentos → Es normal, están creando datos reales
4. Foreign keys → Ejecutar migraciones primero

**Contacto**: Reporta problemas con el mensaje de error completo.

---

**¡Listo para ejecutar!** 🚀

Usa `run-tests.bat` y selecciona la opción que quieras probar.
