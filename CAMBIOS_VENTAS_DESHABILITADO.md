# 🔒 Módulo de Ventas - DESHABILITADO TEMPORALMENTE

**Fecha:** 13 de Octubre, 2026  
**Razón:** Solicitud del usuario - Ocultar módulo de ventas sin eliminarlo

---

## ✅ Cambios Realizados

El módulo de **Ventas** ha sido deshabilitado mediante comentarios en el código. **NO se eliminó nada**, solo se comentó para poder reactivarlo fácilmente.

### 📁 Archivos Modificados:

#### 1. **Menú de Navegación**
**Archivo:** `resources/views/access/navigation.blade.php`
```php
// Línea comentada:
// ['Ventas','cart',['sales.view'=>'sales.index']],
```
✅ **Resultado:** "Ventas" ya NO aparece en el menú lateral

---

#### 2. **Rutas Web**
**Archivo:** `routes/web.php`
```php
// Rutas comentadas:
// Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
// Route::post('/sales/contracts/{contract}/confirm', [SaleController::class, 'confirm'])->...
```
✅ **Resultado:** Las URLs `/sales` y `/sales/contracts/{id}/confirm` ya NO están disponibles

---

#### 3. **Catálogo de Permisos**
**Archivo:** `app/Support/AccessCatalog.php`

**Módulo deshabilitado:**
```php
// 'sales' => 'Ventas',  // COMENTADO
```

**Permisos deshabilitados:**
```php
// 'sales.view' => [...]    // COMENTADO
// 'sales.create' => [...]  // COMENTADO
```

**Dependencias deshabilitadas:**
```php
// if ($code === 'sales.create') return ['sales.view','contracts.view'];  // COMENTADO
```

**Roles actualizados:**
```php
// ROL OFFICE - sales.view y sales.create COMENTADOS
// ROL INSTALLATIONS - sales.view COMENTADO
```

**Rutas deshabilitadas:**
```php
// if ($name === 'sales.confirm') return 'sales.create';  // COMENTADO
```

✅ **Resultado:** Los permisos `sales.view` y `sales.create` ya NO están disponibles

---

#### 4. **Aliases de Permisos**
**Archivo:** `app/Providers/AppServiceProvider.php`
```php
// ANTES:
'view-office' => ['sales.view', 'contracts.view', 'plans.view', 'payments.view', 'reports.view'],

// AHORA:
'view-office' => ['contracts.view', 'plans.view', 'payments.view', 'reports.view'],
```
✅ **Resultado:** El alias `view-office` ya NO incluye `sales.view`

---

## 🎯 Efectos Visibles:

### ✅ Lo que YA NO se ve:
1. ❌ **Menú "Ventas"** en el sidebar
2. ❌ **Ruta `/sales`** (devuelve 404)
3. ❌ **Permisos de ventas** en configuración de roles
4. ❌ **Botón "Registrar venta"** en contratos (si usaba `@can('sales.create')`)

### ✅ Lo que SÍ sigue funcionando:
1. ✅ **Contratos** funcionan normal
2. ✅ **Pagos** funcionan normal
3. ✅ **Dashboard** sigue mostrando gráficos de ventas (si están basados en contratos)
4. ✅ **Datos de ventas en BD** permanecen intactos

---

## 🔄 Cómo REACTIVAR el módulo:

Para volver a habilitar "Ventas", simplemente **descomentar** las líneas en estos 4 archivos:

### 1. `resources/views/access/navigation.blade.php`
```php
// Descomentar esta línea:
['Ventas','cart',['sales.view'=>'sales.index']],
```

### 2. `routes/web.php`
```php
// Descomentar estas líneas:
Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
Route::post('/sales/contracts/{contract}/confirm', [SaleController::class, 'confirm'])->...
```

### 3. `app/Support/AccessCatalog.php`
```php
// Descomentar en MODULES:
'sales' => 'Ventas',

// Descomentar en permissions():
'sales.view' => ['Consultar', '...'],
'sales.create' => ['Registrar venta por firma', '...'],

// Descomentar en dependencies():
if ($code === 'sales.create') return ['sales.view','contracts.view'];

// Descomentar en defaults() ROL OFFICE:
'sales.view','sales.create',

// Descomentar en defaults() ROL INSTALLATIONS:
'sales.view',

// Descomentar en forRoute():
if ($name === 'sales.confirm') return 'sales.create';
```

### 4. `app/Providers/AppServiceProvider.php`
```php
// Descomentar en $aliases:
'view-office' => ['sales.view', 'contracts.view', 'plans.view', 'payments.view', 'reports.view'],
```

---

## 📝 Notas Importantes:

1. **Base de Datos:** La tabla `ventas` y sus datos **NO fueron tocados**
2. **Controller:** `SaleController.php` sigue existiendo y funcionando
3. **Vistas:** Las vistas en `resources/views/sales/` siguen existiendo
4. **Reversible:** Todos los cambios son **100% reversibles** quitando los comentarios

---

## ⚠️ Advertencias:

- Si existen **enlaces directos** a `/sales` en otras partes del código, devolverán 404
- Si existen **validaciones `@can('sales.view')`** en vistas, esas secciones se ocultarán
- Los **seeders de roles** no incluirán permisos de ventas en nuevas instalaciones

---

## 🎯 Estado Final:

| Aspecto | Estado |
|---------|--------|
| **Menú visible** | ❌ Oculto |
| **Rutas accesibles** | ❌ Deshabilitadas (404) |
| **Permisos disponibles** | ❌ No aparecen |
| **Datos en BD** | ✅ Intactos |
| **Código del módulo** | ✅ Intacto (solo comentado) |
| **Reversible** | ✅ 100% |

---

**Preparado por:** Kiro AI  
**Tipo de cambio:** NO DESTRUCTIVO - Solo comentarios  
**Reversión:** Quitar comentarios en 4 archivos
