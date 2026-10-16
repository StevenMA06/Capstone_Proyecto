# 🎯 DATA PROVIDERS - Tests Destructivos

## ¿Qué son los Data Providers?

Los Data Providers ejecutan **EL MISMO TEST** con **MÚLTIPLES DATOS DIFERENTES**, intentando romper el sistema.

## 📊 Tests con Data Providers Implementados

### 1️⃣ `test_store_rechaza_clientes_invalidos` - 7 casos

Intenta crear contratos con clientes inválidos:

| # | Caso | Valor | ¿Debería Fallar? |
|---|------|-------|------------------|
| 1 | Cliente inexistente | `99999` | ✅ SÍ |
| 2 | Cliente cero | `0` | ✅ SÍ |
| 3 | Cliente negativo | `-1` | ✅ SÍ |
| 4 | Cliente texto | `'ABC'` | ✅ SÍ |
| 5 | Cliente NULL | `null` | ✅ SÍ |
| 6 | Cliente array | `[1,2]` | ✅ SÍ |
| 7 | Cliente muy grande | `999999999` | ✅ SÍ |

**Total: 7 intentos de romper el sistema**

---

### 2️⃣ `test_store_rechaza_duraciones_invalidas` - 10 casos

Intenta crear contratos con duraciones inválidas:

| # | Caso | Valor | ¿Debería Fallar? |
|---|------|-------|------------------|
| 1 | Duración negativa | `-5` | ✅ SÍ |
| 2 | Duración cero | `0` | ✅ SÍ |
| 3 | Duración texto | `'doce'` | ✅ SÍ |
| 4 | Duración decimal | `12.5` | ✅ SÍ |
| 5 | Duración NULL | `null` | ✅ SÍ |
| 6 | Duración string número | `'12'` | ⚠️  Puede pasar |
| 7 | Duración muy grande | `999` | ⚠️  Puede pasar |
| 8 | Duración array | `[12]` | ✅ SÍ |
| 9 | Duración negativa grande | `-100` | ✅ SÍ |
| 10 | Duración float negativo | `-5.5` | ✅ SÍ |

**Total: 10 intentos de romper el sistema**

---

### 3️⃣ `test_store_rechaza_fechas_invalidas` - 8 casos

Intenta crear contratos con fechas inválidas:

| # | Caso | Valor | ¿Debería Fallar? |
|---|------|-------|------------------|
| 1 | 30 de febrero | `'2024-02-30'` | ✅ SÍ |
| 2 | 32 de enero | `'2024-01-32'` | ✅ SÍ |
| 3 | Mes 13 | `'2024-13-01'` | ✅ SÍ |
| 4 | Fecha texto | `'mañana'` | ✅ SÍ |
| 5 | Formato incorrecto | `'13/10/2024'` | ✅ SÍ |
| 6 | Solo año | `'2024'` | ✅ SÍ |
| 7 | Fecha NULL | `null` | ✅ SÍ |
| 8 | Fecha vacía | `''` | ✅ SÍ |

**Total: 8 intentos de romper el sistema**

---

### 4️⃣ `test_store_rechaza_o_escapa_entradas_maliciosas` - 10 casos

Intenta ATAQUES DE SEGURIDAD:

| # | Tipo Ataque | Payload | Campo | Objetivo |
|---|-------------|---------|-------|----------|
| 1 | SQL Injection | `'; DROP TABLE contratos;--` | direccion | Eliminar tabla |
| 2 | SQL Injection | `'; DELETE FROM contratos;--` | observaciones | Borrar datos |
| 3 | SQL Injection | `' UNION SELECT * FROM users--` | direccion | Robar datos |
| 4 | XSS | `<script>alert("XSS")</script>` | observaciones | Ejecutar JS |
| 5 | XSS | `<img src=x onerror="alert(1)">` | observaciones | Ejecutar JS |
| 6 | XSS | `<iframe src="malicious.com">` | observaciones | Cargar sitio malicioso |
| 7 | HTML Injection | `<h1>Hacked</h1>` | observaciones | Inyectar HTML |
| 8 | XSS | `javascript:alert("XSS")` | observaciones | URL maliciosa |
| 9 | SQL | `admin'-- ` | direccion | Bypass autenticación |
| 10 | NoSQL Injection | `{"$ne": null}` | observaciones | Bypass NoSQL |

**Total: 10 ataques de seguridad**

---

## 📈 RESUMEN TOTAL

| Categoría | Tests | Casos Totales |
|-----------|-------|---------------|
| Clientes inválidos | 1 test | 7 casos |
| Duraciones inválidas | 1 test | 10 casos |
| Fechas inválidas | 1 test | 8 casos |
| Ataques de seguridad | 1 test | 10 casos |
| Tests individuales | 20 tests | 20 casos |
| **TOTAL** | **24 tests** | **55 casos de prueba** |

---

## 🎯 FILOSOFÍA

**NO estamos probando que todo funcione bien.**  
**Estamos intentando ROMPER el sistema para encontrar vulnerabilidades.**

Cada Data Provider ejecuta el mismo test con **datos diferentes**, simulando:
- ❌ Usuarios malintencionados
- ❌ Errores de tipeo
- ❌ Ataques de hackers
- ❌ Datos corruptos
- ❌ Edge cases extremos

---

## 🚀 EJECUCIÓN

```bash
cd C:\xampp\htdocs\Capstone\Capstone_Proyecto
C:\xampp\php\php.exe artisan test testsphpunit/Contratos/ContractCriticalTest.php
```

Verás salida como:

```
✓ store rechaza clientes invalidos with data set "cliente_inexistente" (0.05s)
✓ store rechaza clientes invalidos with data set "cliente_cero" (0.04s)
✓ store rechaza clientes invalidos with data set "cliente_negativo" (0.04s)
...
```

Cada línea = **1 intento de romper el sistema**

---

## ⚠️ IMPORTANTE

Si algún caso **PASA cuando debería FALLAR**, significa que encontraste una **VULNERABILIDAD** en el código que hay que corregir.

Ejemplo:
```
✓ store rechaza clientes invalidos with data set "cliente_texto"
```

Si este test **FALLA**, significa que el sistema **SÍ aceptó** `cliente_id = 'ABC'`, lo cual es un **BUG**.
