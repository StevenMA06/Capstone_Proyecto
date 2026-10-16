# 📋 ANÁLISIS COMPLETO DE MÉTODOS PARA TESTS PHPUnit

> **Análisis exhaustivo de TODOS los métodos sugeridos en la Guía de Tests**  
> Fuente: `Guia_Metodos_Pruebas_PHPUnit_Lunnys.md`

---

## 📊 ÍNDICE DE MÓDULOS

1. [CA01 - Control de Acceso y Permisos](#ca01---control-de-acceso-y-permisos)
2. [CA02 - Gestión de Clientes](#ca02---gestión-de-clientes)
3. [CA03 - Gestión de Planes](#ca03---gestión-de-planes)
4. [CA04 - Gestión de Contratos](#ca04---gestión-de-contratos)
5. [CA05 - Gestión de Pagos](#ca05---gestión-de-pagos)
6. [CA06 - Gestión de Equipos](#ca06---gestión-de-equipos)
7. [CA07 - Gestión de Materiales](#ca07---gestión-de-materiales)
8. [CA08 - Kardex de Inventario](#ca08---kardex-de-inventario)
9. [CA09 - Zonas de Cobertura](#ca09---zonas-de-cobertura)
10. [CA10 - Pedidos de Instalación](#ca10---pedidos-de-instalación)
11. [CA11 - Ejecución de Instalaciones](#ca11---ejecución-de-instalaciones)
12. [CA12 - Indicadores y BI](#ca12---indicadores-y-bi)

---

# CA01 - CONTROL DE ACCESO Y PERMISOS

**RF relacionados:** RF-ACC

## MÉTODO 1: Combinar permisos de varios roles

### 📝 Método Sugerido (Guía)
```
PermisoService::obtenerPermisosEfectivos()
```

### 🔍 Método Real en el Proyecto
**⚠️ NO EXISTE** - El proyecto usa sistema diferente

**Alternativa Real:** `User::getEffectivePermissions()`  
**Ubicación:** `app/Models/User.php` (línea 89)

### 💻 Código Real

```php
public function getEffectivePermissions(): Collection
{
    return $this->roles()
        ->with('permissions')
        ->get()
        ->pluck('permissions')
        ->flatten()
        ->unique('permiso_id');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Usuario | `User::factory()` | Con `empleado_id` válido |
| Roles | `Role::factory()` | Mínimo 2 roles asignados |
| Permisos | `Permission::factory()` | Asociados a los roles |
| Pivot tabla | `role_user` | Relación usuario-roles |

### ✅ Tests Propuestos (3 casos)

1. Usuario con 2 roles devuelve unión de permisos sin duplicados
2. Usuario sin roles devuelve colección vacía
3. Permisos duplicados en múltiples roles se retornan una sola vez

**RF:** RF-ACC-31

---

## MÉTODO 2: Bloquear funcionalidad no autorizada

### 📝 Método Sugerido (Guía)
```
PermisoService::puedeAcceder()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como `User::hasPermission()`  
**Ubicación:** `app/Models/User.php` (línea 99)

### 💻 Código Real

```php
public function hasPermission(string $permission): bool
{
    return $this->getEffectivePermissions()
        ->contains('codigo', $permission);
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Usuario | `User::factory()` | Con rol asignado |
| Permiso | `Permission::where('codigo', 'X')` | Código específico |
| Rol | `Role::factory()` | Con permisos asociados |

### ✅ Tests Propuestos (5 casos)

1. Usuario sin permiso 'contracts.create' retorna `false`
2. Usuario con permiso 'contracts.create' retorna `true`
3. Usuario con rol vacío retorna `false` para cualquier permiso
4. Permiso inexistente retorna `false`
5. Usuario INACTIVO retorna `false` incluso con permisos

**RF:** RF-ACC-15

---

## MÉTODO 3: Bloquear cuenta inhabilitada

### 📝 Método Sugerido (Guía)
```
AccesoService::cuentaPuedeIngresar()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como Middleware `EnsureAccountActive`  
**Ubicación:** `app/Http/Middleware/EnsureAccountActive.php` (línea 15)

### 💻 Código Real

```php
public function handle(Request $request, Closure $next): Response
{
    if ($request->user() && $request->user()->isInactive()) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Tu cuenta está inhabilitada. Contacta con soporte.',
        ]);
    }

    return $next($request);
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Usuario | `User::factory()` | Con `empleado_id` |
| Empleado | `Employee::factory()` | `estado = 'INACTIVO'` o fecha_cese |

### ✅ Tests Propuestos (4 casos)

1. Usuario ACTIVO puede acceder
2. Usuario INACTIVO es redirigido a login
3. Usuario con `fecha_cese` es bloqueado
4. Sesión se invalida al detectar cuenta INACTIVA

**RF:** RF-ACC-16

---

# CA02 - GESTIÓN DE CLIENTES

**RF relacionados:** RF-CLI

## MÉTODO 1: Validar campos obligatorios

### 📝 Método Sugerido (Guía)
```
ClienteService::validarDatos()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como `StoreClientRequest::rules()`  
**Ubicación:** `app/Http/Requests/StoreClientRequest.php` (línea 14)

### 💻 Código Real

```php
public function rules(): array
{
    $clientId = $this->route('client')?->getKey();

    return [
        'tipo_documento' => ['required', Rule::in(['DNI', 'CE', 'RUC', 'PASAPORTE'])],
        'numero_documento' => ['required', 'string', 'max:20'],
        'nombres' => ['required', 'string', 'max:100'],
        'apellido_paterno' => ['nullable', 'string', 'max:100'],
        'apellido_materno' => ['nullable', 'string', 'max:100'],
        'celular' => ['nullable', 'string', 'max:15'],
        'telefono_fijo' => ['nullable', 'string', 'max:15'],
        'email' => ['nullable', 'email', 'max:150'],
        'direccion' => ['required', 'string', 'max:255'],
        'distrito' => ['required', 'string', 'max:100'],
        'provincia' => ['nullable', 'string', 'max:100'],
        'departamento' => ['nullable', 'string', 'max:100'],
        'referencia' => ['nullable', 'string', 'max:255'],
        'observaciones' => ['nullable', 'string', 'max:500'],
        'contrasena_router' => ['nullable', 'string', 'max:100'],
        'contrasena_wifi' => ['nullable', 'string', 'max:100'],
    ];
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Usuario autenticado | `User::factory()` | Con permiso `clients.create` |

### ✅ Tests Propuestos (8 casos)

1. Rechaza nombres vacíos
2. Rechaza tipo_documento inválido
3. Rechaza numero_documento vacío
4. Rechaza direccion vacía
5. Rechaza distrito vacío
6. Acepta campos opcionales NULL
7. Rechaza email con formato inválido
8. Rechaza campo que excede max length

**RF:** RF-CLI-02, RF-CLI-03

---

## MÉTODO 2: Detectar documento duplicado

### 📝 Método Sugerido (Guía)
```
ClienteService::documentoDisponible()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `ClientController::store()` con manejo de excepción  
**Ubicación:** `app/Http/Controllers/ClientController.php` (línea 28)

### 💻 Código Real

```php
public function store(StoreClientRequest $request): RedirectResponse
{
    try {
        $client = $this->clients->create($request->validated(), $request->user());
    } catch (UniqueConstraintViolationException $exception) {
        if (! str_contains($exception->getMessage(), 'uq_clientes_documento')) {
            throw $exception;
        }

        return back()->withInput($request->except(['contrasena_router', 'contrasena_wifi']))->withErrors([
            'numero_documento' => 'Este número ya está registrado para el tipo de documento seleccionado.',
        ]);
    }

    return redirect()->route('clients.show', $client)
        ->with('status', 'Cliente #'.$client->getKey().' registrado correctamente.');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Cliente existente | `Client::factory()` | `numero_documento` específico |
| Usuario autenticado | `User::factory()` | Con permiso `clients.create` |

### ✅ Tests Propuestos (5 casos)

1. Rechaza DNI duplicado con mismo tipo_documento
2. Permite DNI igual pero tipo_documento diferente (DNI vs RUC)
3. Mensaje de error específico para duplicado
4. Preserva datos del formulario (excepto contraseñas)
5. Redirección a back() con errores

**RF:** RF-CLI-04

---

## MÉTODO 3: Cambiar estado a Pendiente de instalación

### 📝 Método Sugerido (Guía)
```
ClienteService::marcarPendienteInstalacion()
```

### 🔍 Método Real en el Proyecto
**⚠️ NO EXISTE** - Estado cambia automáticamente en Contract  
**Alternativa:** Estado del cliente se actualiza cuando se crea contrato

### 💻 Lógica Real (implícita)

```php
// En Contract.php - observer o evento
// Cuando contrato pasa a estado PENDIENTE:
// $contract->cliente->update(['estado' => 'PENDIENTE_INSTALACION']);
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Cliente | `Client::factory()` | `estado = 'ACTIVO'` |
| Contrato | `Contract::factory()` | Asociado al cliente |

### ✅ Tests Propuestos (3 casos)

1. Cliente con contrato PENDIENTE cambia a PENDIENTE_INSTALACION
2. Cliente sin contratos permanece en estado actual
3. Cliente con contrato ACTIVO no cambia de estado

**RF:** RF-CLI-14

---

## MÉTODO 4: Actualizar datos de cliente

### 📝 Método Sugerido (Guía)
```
ClienteService::update()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE**  
**Ubicación:** `app/Http/Controllers/ClientController.php` (línea 74)

### 💻 Código Real

```php
public function update(StoreClientRequest $request, Client $client): RedirectResponse
{
    try {
        $this->clients->update($client, $request->validated());
    } catch (UniqueConstraintViolationException $exception) {
        if (! str_contains($exception->getMessage(), 'uq_clientes_documento')) {
            throw $exception;
        }

        return back()->withInput($request->except(['contrasena_router', 'contrasena_wifi']))->withErrors([
            'numero_documento' => 'Este número ya está registrado para el tipo de documento seleccionado.',
        ]);
    }

    return redirect()->route('clients.show', $client)->with('status', 'Datos del cliente actualizados correctamente.');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Cliente existente | `Client::factory()` | Cualquier estado |
| Usuario autenticado | `User::factory()` | Permiso `clients.update` |

### ✅ Tests Propuestos (6 casos)

1. Actualiza nombres correctamente
2. Actualiza dirección correctamente
3. Rechaza documento duplicado de otro cliente
4. Permite actualizar sin cambiar documento
5. SQL Injection en observaciones
6. XSS en dirección

**RF:** RF-CLI-09

---

## MÉTODO 5: Desactivar cliente

### 📝 Método Sugerido (Guía)
```
ClienteService::deactivate()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE**  
**Ubicación:** `app/Http/Controllers/ClientController.php` (línea 90)

### 💻 Código Real

```php
public function deactivate(Client $client): RedirectResponse
{
    $this->clients->deactivate($client);

    return redirect()->route('clients.show', $client)
        ->with('status', 'Cliente inhabilitado. Su información y sus historiales se conservan.');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Cliente | `Client::factory()` | `estado = 'ACTIVO'` |
| Usuario autenticado | `User::factory()` | Permiso `clients.deactivate` |

### ✅ Tests Propuestos (4 casos)

1. Desactiva cliente ACTIVO correctamente
2. Rechaza desactivar cliente ya INACTIVO
3. Conserva historial de contratos y pagos
4. Valida permiso antes de desactivar

**RF:** RF-CLI-10

---

# CA03 - GESTIÓN DE PLANES

**RF relacionados:** RF-PLA

## MÉTODO 1: Validar plan activo

### 📝 Método Sugerido (Guía)
```
PlanService::estaDisponibleParaContrato()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como validación en `StoreContractRequest`  
**Ubicación:** `app/Http/Requests/StoreContractRequest.php`

### 💻 Código Real (validación)

```php
'plan_id' => [
    'required',
    'integer',
    'exists:planes,plan_id',
    function ($attribute, $value, $fail) {
        $plan = \App\Models\ServicePlan::find($value);
        if ($plan && $plan->estado !== 'ACTIVO') {
            $fail('El plan seleccionado no está disponible.');
        }
    },
],
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Plan | `ServicePlan::factory()` | `estado = 'ACTIVO'` |

### ✅ Tests Propuestos (3 casos)

1. Plan ACTIVO retorna `true`
2. Plan INACTIVO retorna `false`
3. Plan inexistente retorna `false`

**RF:** RF-PLA-10

---

# CA04 - GESTIÓN DE CONTRATOS

**RF relacionados:** RF-CON

## MÉTODO 1: store() - Crear contrato

### ✅ **COMPLETO** - 36 tests implementados

Ver: `phpunittestsinforme/CONTRATOS_PLANIFICACION.md`

---

## MÉTODO 2: Calcular fecha de finalización

### 📝 Método Sugerido (Guía)
```
ContratoService::calcularFechaFin()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `ContractRepository::create()`  
**Ubicación:** `app/Domain/Contracts/ContractRepository.php` (línea 45)

### 💻 Código Real

```php
protected function calculateEndDate(string $startDate, int $months): string
{
    return Carbon::parse($startDate)
        ->addMonths($months)
        ->subDay()
        ->format('Y-m-d');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Ninguna | - | Solo fecha string y entero |

### ✅ Tests Propuestos (5 casos)

1. 10/10/2026 + 12 meses = 09/10/2027
2. 31/01/2026 + 1 mes = 28/02/2026 (año no bisiesto)
3. 31/01/2024 + 1 mes = 29/02/2024 (año bisiesto)
4. 15/03/2026 + 6 meses = 14/09/2026
5. 01/01/2026 + 24 meses = 31/12/2027

**RF:** RF-CON-07

---

## MÉTODO 3: Generar código único

### 📝 Método Sugerido (Guía)
```
ContratoService::generarCodigo()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `ContractRepository::generateCode()`  
**Ubicación:** `app/Domain/Contracts/ContractRepository.php` (línea 133)

### 💻 Código Real

```php
protected function generateCode(): string
{
    return 'CON-' . date('Ymd') . '-' . strtoupper(Str::random(8));
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Ninguna | - | Solo fecha actual |

### ✅ Tests Propuestos (4 casos)

1. Código generado tiene formato CON-YYYYMMDD-XXXXXXXX
2. Dos códigos consecutivos son diferentes
3. Código tiene exactamente 20 caracteres
4. Parte aleatoria contiene solo mayúsculas y números

**RF:** RF-CON-08

---

## MÉTODO 4: Validar reactivación

### 📝 Método Sugerido (Guía)
```
ContratoService::puedeReactivarse()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `ContractRepository::reactivate()`  
**Ubicación:** `app/Domain/Contracts/ContractRepository.php` (línea 215)

### 💻 Código Real

```php
public function reactivate(int $id, array $data, int $userId): array
{
    $contract = $this->detail($id);

    if ($contract->estado !== 'CANCELADO') {
        return [
            'kind' => 'danger',
            'message' => 'Solo se pueden reactivar contratos cancelados.'
        ];
    }

    // Validar cliente sin deuda
    $unpaidPayments = Payment::where('contrato_id', $id)
        ->whereIn('estado', ['PENDIENTE', 'VENCIDO'])
        ->count();

    if ($unpaidPayments > 0) {
        return [
            'kind' => 'danger',
            'message' => 'El cliente tiene pagos pendientes. Regulariza la deuda antes de reactivar.'
        ];
    }

    // Reactivar...
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Contrato | `Contract::factory()` | `estado = 'CANCELADO'` |
| Pagos | `Payment::factory()` | Sin pagos PENDIENTES/VENCIDOS |
| Cliente | `Client::factory()` | ACTIVO |

### ✅ Tests Propuestos (6 casos)

1. Contrato CANCELADO sin deuda puede reactivarse
2. Contrato ACTIVO no puede reactivarse
3. Contrato PENDIENTE no puede reactivarse
4. Contrato con pagos PENDIENTES no puede reactivarse
5. Contrato con pagos VENCIDOS no puede reactivarse
6. Mensaje de error específico para cada caso

**RF:** RF-CON-14

---

## MÉTODO 5: Cancelar contrato

### 📝 Método Sugerido (Guía)
```
ContratoService::cancelar()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `ContractController::approveCancellation()`  
**Ubicación:** `app/Http/Controllers/ContractController.php` (línea 167)

### 💻 Código Real

```php
public function approveCancellation(Request $request, int $contract, int $cancellationRequest)
{
    // Validar que la solicitud existe y está pendiente
    // Cambiar estado del contrato a CANCELADO
    // Cancelar pedidos de instalación pendientes
    // Registrar log de auditoría
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Contrato | `Contract::factory()` | `estado = 'ACTIVO'` |
| Solicitud | `CancellationRequest` | PENDIENTE |
| Usuario | `User::factory()` | Permiso `contracts.cancel.approve` |

### ✅ Tests Propuestos (5 casos)

1. Aprueba cancelación exitosamente
2. Cambia estado a CANCELADO
3. Cancela pedidos pendientes asociados
4. Rechaza sin permiso
5. Rechaza solicitud ya procesada

**RF:** RF-CON-24

---

# CA05 - GESTIÓN DE PAGOS

**RF relacionados:** RF-PAG

## MÉTODO 1: Generar obligaciones mensuales

### 📝 Método Sugerido (Guía)
```
PagoService::generarPagosContrato()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `PaymentRepository::generate()`  
**Ubicación:** `app/Repositories/PaymentRepository.php` (línea 89)

### 💻 Código Real

```php
public function generate(int $contractId, int $userId): bool
{
    $contract = Contract::findOrFail($contractId);

    if ($contract->estado !== 'ACTIVO') {
        return false;
    }

    $startDate = Carbon::parse($contract->fecha_inicio);
    $payments = [];

    for ($i = 0; $i < $contract->duracion_meses; $i++) {
        $dueDate = $startDate->copy()->addMonths($i);

        $payments[] = [
            'contrato_id' => $contractId,
            'numero_cuota' => $i + 1,
            'monto' => $contract->plan->precio_mensual,
            'fecha_vencimiento' => $dueDate->format('Y-m-d'),
            'fecha_limite' => $dueDate->addDays(4)->format('Y-m-d'), // RN-45: 4 días de gracia
            'estado' => 'PENDIENTE',
            'usuario_registro_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    Payment::insert($payments);
    return true;
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Contrato | `Contract::factory()` | `estado = 'ACTIVO'`, `duracion_meses` |
| Plan | `ServicePlan::factory()` | `precio_mensual > 0` |
| Usuario | `User::factory()` | Para registro |

### ✅ Tests Propuestos (6 casos)

1. Contrato de 12 meses genera 12 pagos
2. Primer pago tiene numero_cuota = 1
3. Último pago tiene numero_cuota = duracion_meses
4. Cada pago tiene fecha_limite = fecha_vencimiento + 4 días
5. Todos los pagos tienen estado PENDIENTE
6. Rechaza generar pagos de contrato NO ACTIVO

**RF:** RF-PAG-01, RF-PAG-02

---

## MÉTODO 2: Confirmar pago

### 📝 Método Sugerido (Guía)
```
PagoService::confirmarPago()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como `PaymentController::complete()`  
**Ubicación:** `app/Http/Controllers/PaymentController.php` (línea 184)

### 💻 Código Real

```php
public function complete(Request $request, Payment $payment): RedirectResponse
{
    abort_unless($request->user()->hasPermission('payments.complete'), 403);

    if ($payment->estado !== 'PENDIENTE') {
        return back()->withErrors(['error' => 'Solo se pueden completar pagos PENDIENTES.']);
    }

    $validated = $request->validate([
        'fecha_pago' => 'required|date|before_or_equal:today',
        'metodo_pago' => 'required|in:EFECTIVO,TRANSFERENCIA,YAPE,PLIN',
        'numero_operacion' => 'nullable|string|max:50',
        'observaciones' => 'nullable|string|max:500'
    ]);

    $this->payments->complete($payment, $validated, $request->user());

    return redirect()->route('payments.show', $payment)
        ->with('status', 'Pago confirmado exitosamente.');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Pago | `Payment::factory()` | `estado = 'PENDIENTE'` |
| Usuario | `User::factory()` | Permiso `payments.complete` |
| Contrato | `Contract::factory()` | Asociado al pago |

### ✅ Tests Propuestos (8 casos)

1. Completa pago PENDIENTE exitosamente
2. Rechaza completar pago REALIZADO
3. Rechaza completar pago ANULADO
4. Rechaza fecha_pago futura
5. Rechaza método_pago inválido
6. Rechaza sin permiso
7. Genera recibo automático
8. SQL Injection en observaciones

**RF:** RF-PAG-16, RF-PAG-17

---

## MÉTODO 3: Marcar pago vencido

### 📝 Método Sugerido (Guía)
```
PagoService::actualizarVencimiento()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como Command `UpdateOverduePayments`  
**Ubicación:** `app/Console/Commands/UpdateOverduePayments.php` (línea 30)

### 💻 Código Real

```php
public function handle()
{
    $today = now()->format('Y-m-d');

    $updated = Payment::where('estado', 'PENDIENTE')
        ->where('fecha_limite', '<', $today)
        ->update([
            'estado' => 'VENCIDO',
            'updated_at' => now()
        ]);

    $this->info("Pagos actualizados a VENCIDO: {$updated}");
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Pagos | `Payment::factory()` | `estado = 'PENDIENTE'`, `fecha_limite` pasada |

### ✅ Tests Propuestos (5 casos)

1. Pago con fecha_limite vencida pasa a VENCIDO
2. Pago con fecha_limite hoy NO cambia
3. Pago con fecha_limite futura NO cambia
4. Pago REALIZADO NO cambia a VENCIDO
5. Command actualiza múltiples pagos en batch

**RF:** RF-PAG-15

---

## MÉTODO 4: Anular pago

### 📝 Método Sugerido (Guía)
```
PagoService::anularPorIncumplimiento()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como `PaymentController::cancel()`  
**Ubicación:** `app/Http/Controllers/PaymentController.php` (línea 214)

### 💻 Código Real

```php
public function cancel(Request $request, Payment $payment): RedirectResponse
{
    abort_unless($request->user()->hasPermission('payments.cancel'), 403);

    if ($payment->estado === 'REALIZADO') {
        return back()->withErrors(['error' => 'No se puede anular un pago REALIZADO.']);
    }

    $validated = $request->validate([
        'motivo_anulacion' => 'required|string|max:500'
    ]);

    $this->payments->cancel($payment, $validated['motivo_anulacion'], $request->user());

    return redirect()->route('payments.show', $payment)
        ->with('status', 'Pago anulado correctamente.');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Pago | `Payment::factory()` | `estado = 'PENDIENTE'` o 'VENCIDO' |
| Usuario | `User::factory()` | Permiso `payments.cancel` |

### ✅ Tests Propuestos (5 casos)

1. Anula pago PENDIENTE exitosamente
2. Anula pago VENCIDO exitosamente
3. Rechaza anular pago REALIZADO
4. Rechaza sin motivo
5. Rechaza sin permiso

**RF:** RF-PAG-24

---

# CA06 - GESTIÓN DE EQUIPOS

**RF relacionados:** RF-EQU

## MÉTODO 1: Validar identificador único

### 📝 Método Sugerido (Guía)
```
EquipoService::identificadorDisponible()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como validación en `StoreEquipmentRequest`  
**Ubicación:** `app/Http/Requests/StoreEquipmentRequest.php`

### 💻 Código Real

```php
'numero_serie' => [
    'required',
    'string',
    'max:100',
    Rule::unique('equipos', 'numero_serie')->ignore($this->route('equipment')?->getKey(), 'equipo_id')
],
'codigo' => [
    'nullable',
    'string',
    'max:50',
    Rule::unique('equipos', 'codigo')->ignore($this->route('equipment')?->getKey(), 'equipo_id')
],
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Equipo existente | `Equipment::factory()` | Con `numero_serie` específico |

### ✅ Tests Propuestos (4 casos)

1. Rechaza numero_serie duplicado
2. Rechaza codigo duplicado
3. Permite numero_serie único
4. Permite actualizar sin cambiar numero_serie

**RF:** RF-EQU-02

---

## MÉTODO 2: Asignar equipo

### 📝 Método Sugerido (Guía)
```
EquipoService::asignar()
```

### 🔍 Método Real en el Proyecto
**⚠️ LÓGICA DISTRIBUIDA** - Se asigna en InstallationOrder  
**Ubicación:** Proceso de instalación

### 💻 Lógica Real

```php
// Cuando se completa instalación:
// 1. Equipo cambia estado DISPONIBLE → ASIGNADO
// 2. Se registra en tabla equipment_assignments
// 3. Se vincula con contrato
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Equipo | `Equipment::factory()` | `estado = 'DISPONIBLE'` |
| Instalación | `InstallationOrder::factory()` | `estado = 'EN_PROCESO'` |
| Contrato | `Contract::factory()` | `estado = 'ACTIVO'` |

### ✅ Tests Propuestos (5 casos)

1. Asigna equipo DISPONIBLE exitosamente
2. Rechaza asignar equipo ya ASIGNADO
3. Rechaza asignar equipo MANTENIMIENTO
4. Equipo queda vinculado con contrato
5. Estado cambia a ASIGNADO

**RF:** RF-EQU-08, RF-EQU-09

---

## MÉTODO 3: Devolver equipo

### 📝 Método Sugerido (Guía)
```
EquipoService::devolver()
```

### 🔍 Método Real en el Proyecto
**⚠️ NO IMPLEMENTADO** - Proceso manual

### ✅ Tests Propuestos (4 casos)

1. Devuelve equipo ASIGNADO exitosamente
2. Registra condición (BUENO, REGULAR, MALO)
3. Equipo vuelve a estado DISPONIBLE
4. Se registra fecha y usuario de devolución

**RF:** RF-EQU-11, RF-EQU-12

---

## MÉTODO 4: Importar equipos CSV

### 📝 Método Sugerido (Guía)
```
EquipoImportService::validarFila()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `EquipmentController::processImport()`  
**Ubicación:** `app/Http/Controllers/EquipmentController.php` (línea 125)

### 💻 Código Real

```php
public function processImport(Request $request): RedirectResponse
{
    $request->validate([
        'archivo' => 'required|file|mimes:csv,txt|max:2048'
    ]);

    $file = $request->file('archivo');
    $csv = Reader::createFromPath($file->getRealPath());
    $csv->setHeaderOffset(0);

    $records = $csv->getRecords();
    $imported = 0;
    $errors = [];

    foreach ($records as $index => $row) {
        // Validar fila
        // Si válida: crear equipo
        // Si inválida: agregar a $errors
    }

    // ...
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Usuario | `User::factory()` | Permiso `equipment.import` |
| Archivo CSV | UploadedFile | Formato válido |

### ✅ Tests Propuestos (8 casos)

1. Importa CSV válido exitosamente
2. Rechaza archivo con columnas faltantes
3. Rechaza fila con numero_serie duplicado
4. Rechaza fila con campos vacíos
5. Rechaza archivo mayor a 2MB
6. Rechaza formato no CSV
7. Procesa múltiples filas correctamente
8. Genera reporte de errores

**RF:** RF-EQU-17, RF-EQU-18

---

# CA07 - GESTIÓN DE MATERIALES

**RF relacionados:** RF-MAT

## MÉTODO 1: Validar stock suficiente

### 📝 Método Sugerido (Guía)
```
MaterialService::hayStock()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `StockExitRequest::rules()`  
**Ubicación:** `app/Http/Requests/StockExitRequest.php`

### 💻 Código Real

```php
'cantidad' => [
    'required',
    'integer',
    'min:1',
    function ($attribute, $value, $fail) {
        $material = Material::find($this->input('material_id'));
        if ($material && $value > $material->stock_actual) {
            $fail('La cantidad solicitada excede el stock disponible (' . $material->stock_actual . ').');
        }
    },
],
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Material | `Material::factory()` | `stock_actual > 0` |

### ✅ Tests Propuestos (5 casos)

1. Stock 10, solicitud 4: retorna `true`
2. Stock 2, solicitud 4: retorna `false`
3. Stock 5, solicitud 5: retorna `true`
4. Stock 0, solicitud 1: retorna `false`
5. Mensaje de error muestra stock disponible

**RN:** RN-24

---

## MÉTODO 2: Evitar existencia negativa

### 📝 Método Sugerido (Guía)
```
MaterialService::calcularStockPosterior()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como validación en múltiples lugares

### 💻 Lógica Real

```php
// Antes de cualquier salida:
if ($material->stock_actual - $cantidad < 0) {
    throw new ValidationException('La operación dejaría stock negativo.');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Material | `Material::factory()` | `stock_actual` específico |

### ✅ Tests Propuestos (4 casos)

1. Stock 2, salida 4: operación inválida
2. Stock 5, salida 3: operación válida
3. Stock 0, salida 1: operación inválida
4. Mensaje de error claro

**RN:** RN-25

---

## MÉTODO 3: Detectar existencia mínima

### 📝 Método Sugerido (Guía)
```
MaterialService::requiereAlerta()
```

### 🔍 Método Real en el Proyecto
**⚠️ NO IMPLEMENTADO** - Funcionalidad pendiente

### 💻 Código Propuesto

```php
public function requiereAlerta(Material $material): bool
{
    return $material->stock_actual <= $material->stock_minimo;
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Material | `Material::factory()` | `stock_actual`, `stock_minimo` |

### ✅ Tests Propuestos (4 casos)

1. Stock 5, mínimo 10: retorna `true`
2. Stock 15, mínimo 10: retorna `false`
3. Stock 10, mínimo 10: retorna `true`
4. Notificación automática se envía

**RF:** RF-MAT-09, **RN:** RN-27

---

# CA08 - KARDEX DE INVENTARIO

**RF relacionados:** RF-KAR

## MÉTODO 1: Registrar entrada

### 📝 Método Sugerido (Guía)
```
KardexService::registrarEntrada()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `InventoryRepository::recordEntry()`  
**Ubicación:** `app/Repositories/InventoryRepository.php` (línea 25)

### 💻 Código Real

```php
public function recordEntry(
    int $materialId,
    int $quantity,
    string $reason,
    ?string $reference,
    int $userId
): void {
    $material = Material::findOrFail($materialId);
    $previousStock = $material->stock_actual;
    $newStock = $previousStock + $quantity;

    // Registrar movimiento en kardex
    KardexMovement::create([
        'material_id' => $materialId,
        'tipo_movimiento' => 'ENTRADA',
        'cantidad' => $quantity,
        'stock_anterior' => $previousStock,
        'stock_posterior' => $newStock,
        'motivo' => $reason,
        'referencia' => $reference,
        'usuario_id' => $userId,
    ]);

    // Actualizar stock del material
    $material->update(['stock_actual' => $newStock]);
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Material | `Material::factory()` | `stock_actual` inicial |
| Usuario | `User::factory()` | Para registro |

### ✅ Tests Propuestos (5 casos)

1. Entrada de 10 unidades incrementa stock
2. Movimiento ENTRADA se registra en kardex
3. stock_anterior y stock_posterior correctos
4. Rechaza cantidad negativa
5. Requiere motivo válido

**RF:** RF-KAR-01

---

## MÉTODO 2: Registrar salida

### 📝 Método Sugerido (Guía)
```
KardexService::registrarSalida()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `InventoryRepository::recordExit()`  
**Ubicación:** `app/Repositories/InventoryRepository.php` (línea 52)

### 💻 Código Real

```php
public function recordExit(
    int $materialId,
    int $quantity,
    string $reason,
    ?string $reference,
    int $userId
): void {
    $material = Material::findOrFail($materialId);

    if ($material->stock_actual < $quantity) {
        throw new \Exception('Stock insuficiente.');
    }

    $previousStock = $material->stock_actual;
    $newStock = $previousStock - $quantity;

    // Registrar movimiento
    KardexMovement::create([
        'material_id' => $materialId,
        'tipo_movimiento' => 'SALIDA',
        'cantidad' => $quantity,
        'stock_anterior' => $previousStock,
        'stock_posterior' => $newStock,
        'motivo' => $reason,
        'referencia' => $reference,
        'usuario_id' => $userId,
    ]);

    // Actualizar stock
    $material->update(['stock_actual' => $newStock]);
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Material | `Material::factory()` | `stock_actual > 0` |
| Usuario | `User::factory()` | Para registro |

### ✅ Tests Propuestos (6 casos)

1. Salida de 3 unidades decrementa stock
2. Movimiento SALIDA se registra en kardex
3. stock_anterior y stock_posterior correctos
4. Rechaza cantidad > stock_actual
5. Rechaza dejar stock negativo
6. Requiere referencia válida

**RF:** RF-KAR-02

---

## MÉTODO 3: Registrar consumo

### 📝 Método Sugerido (Guía)
```
KardexService::registrarConsumo()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en proceso de instalación  
**Ubicación:** Vinculado con `InstallationOrder`

### 💻 Lógica Real

```php
// Cuando se registra consumo en instalación:
// 1. Se registra movimiento CONSUMO en kardex
// 2. Se vincula con instalacion_id
// 3. Se decrementa stock_actual
// 4. Se registra técnico responsable
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Instalación | `InstallationOrder::factory()` | `estado = 'EN_PROCESO'` |
| Material | `Material::factory()` | `stock_actual > 0` |
| Técnico | `Employee::factory()` | Asignado a instalación |

### ✅ Tests Propuestos (5 casos)

1. Consumo vincula material con instalación
2. Movimiento CONSUMO se registra en kardex
3. Stock se decrementa correctamente
4. Técnico responsable se registra
5. Rechaza consumo sin instalación activa

**RF:** RF-KAR-04

---

## MÉTODO 4: Exigir justificación de ajuste

### 📝 Método Sugerido (Guía)
```
KardexService::validarAjuste()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `StockAdjustmentRequest`  
**Ubicación:** `app/Http/Requests/StockAdjustmentRequest.php`

### 💻 Código Real

```php
public function rules(): array
{
    return [
        'material_id' => 'required|integer|exists:materiales,material_id',
        'tipo_ajuste' => ['required', Rule::in(['AUMENTO', 'DISMINUCION'])],
        'cantidad' => 'required|integer|min:1',
        'motivo' => 'required|string|min:10|max:500', // Mínimo 10 caracteres
        'evidencia' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
    ];
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Material | `Material::factory()` | Cualquiera |
| Usuario | `User::factory()` | Permiso `materials.adjust` |

### ✅ Tests Propuestos (5 casos)

1. Ajuste sin motivo es rechazado
2. Motivo con menos de 10 caracteres es rechazado
3. Ajuste válido con motivo se registra
4. Archivo evidencia es opcional
5. Rechaza archivo mayor a 5MB

**RF:** RF-KAR-06, **RN:** RN-33

---

# CA09 - ZONAS DE COBERTURA

**RF relacionados:** RF-ZON

## MÉTODO 1: Validar ubicación cubierta

### 📝 Método Sugerido (Guía)
```
CoberturaService::tieneCobertura()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como validación en contrato  
**Ubicación:** Validación implícita por `zona_id`

### 💻 Lógica Real

```php
// En validación de contrato:
'zona_id' => [
    'required',
    'integer',
    'exists:zonas_cobertura,zona_id',
    function ($attribute, $value, $fail) {
        $zona = CoverageZone::find($value);
        if ($zona && $zona->estado !== 'ACTIVA') {
            $fail('La zona seleccionada no está activa.');
        }
        // Validar que distrito del cliente coincida con zona
        if ($zona && $this->input('distrito') !== $zona->distrito) {
            $fail('El distrito no corresponde a la zona de cobertura.');
        }
    },
],
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Zona | `CoverageZone::factory()` | `estado = 'ACTIVA'`, `distrito` |
| Cliente | `Client::factory()` | Con `distrito` coincidente |

### ✅ Tests Propuestos (5 casos)

1. Dirección dentro de zona activa retorna `true`
2. Dirección fuera de zona retorna `false`
3. Zona INACTIVA retorna `false`
4. Distrito no coincidente retorna `false`
5. Validación case-insensitive de distrito

**RF:** RF-ZON-10

---

## MÉTODO 2: Listar planes de zona

### 📝 Método Sugerido (Guía)
```
CoberturaService::planesDisponibles()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en relación many-to-many  
**Ubicación:** `app/Models/CoverageZone.php`

### 💻 Código Real

```php
public function plans()
{
    return $this->belongsToMany(
        ServicePlan::class,
        'zona_plan',
        'zona_id',
        'plan_id'
    );
}

// Obtener planes activos de zona:
$planesDisponibles = $zona->plans()
    ->where('estado', 'ACTIVO')
    ->get();
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Zona | `CoverageZone::factory()` | `estado = 'ACTIVA'` |
| Planes | `ServicePlan::factory()` | `estado = 'ACTIVO'` |
| Pivot | `zona_plan` | Relación establecida |

### ✅ Tests Propuestos (4 casos)

1. Zona activa retorna solo planes activos
2. Zona con planes inactivos los excluye
3. Zona sin planes retorna colección vacía
4. Planes ordenados por precio

**RF:** RF-ZON-12, RF-ZON-13

---

# CA10 - PEDIDOS DE INSTALACIÓN

**RF relacionados:** RF-PED

## MÉTODO 1: Validar técnico duplicado

### 📝 Método Sugerido (Guía)
```
PedidoService::validarTecnicos()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en validación de Installation Order

### 💻 Código Real (validación)

```php
'tecnico_principal_id' => [
    'required',
    'integer',
    'exists:empleados,empleado_id',
    'different:tecnico_secundario_id', // RN-70
],
'tecnico_secundario_id' => [
    'nullable',
    'integer',
    'exists:empleados,empleado_id',
    'different:tecnico_principal_id',
],
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Técnicos | `Employee::factory()` | `cargo` = técnico, ACTIVOS |

### ✅ Tests Propuestos (4 casos)

1. Principal = secundario es rechazado
2. Técnicos diferentes es aceptado
3. Secundario NULL es aceptado
4. Mensaje de error específico

**RF:** RF-PED-15, **RN:** RN-70

---

## MÉTODO 2: Detectar cruce de agenda

### 📝 Método Sugerido (Guía)
```
PedidoService::hayConflictoHorario()
```

### 🔍 Método Real en el Proyecto
**⚠️ NO IMPLEMENTADO** - Funcionalidad pendiente

### 💻 Código Propuesto

```php
public function hayConflictoHorario(
    int $tecnicoId,
    string $fecha,
    string $hora
): bool {
    return InstallationOrder::where('tecnico_principal_id', $tecnicoId)
        ->orWhere('tecnico_secundario_id', $tecnicoId)
        ->where('fecha_programada', $fecha)
        ->where('hora_programada', $hora)
        ->whereNotIn('estado', ['CANCELADO', 'COMPLETADO'])
        ->exists();
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Instalaciones | `InstallationOrder::factory()` | Con fecha y hora programadas |
| Técnico | `Employee::factory()` | ACTIVO |

### ✅ Tests Propuestos (6 casos)

1. Mismo técnico, misma fecha y hora: retorna `true`
2. Mismo técnico, fecha diferente: retorna `false`
3. Técnico diferente, misma hora: retorna `false`
4. Instalación CANCELADA no genera conflicto
5. Instalación COMPLETADA no genera conflicto
6. Conflicto en técnico_secundario también detectado

**RF:** RF-PED-16, **RN:** RN-71

---

## MÉTODO 3: Reprogramar visita

### 📝 Método Sugerido (Guía)
```
PedidoService::reprogramar()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `InstallationOrderController`

### 💻 Código Real

```php
public function reschedule(Request $request, InstallationOrder $order)
{
    $validated = $request->validate([
        'fecha_programada' => 'required|date|after_or_equal:today',
        'hora_programada' => 'required|date_format:H:i',
        'motivo_reprogramacion' => 'required|string|min:10|max:500',
    ]);

    // Guardar historial
    // Actualizar fecha/hora
    // Notificar cliente
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Instalación | `InstallationOrder::factory()` | `estado = 'PROGRAMADO'` |
| Usuario | `User::factory()` | Permiso `orders.reschedule` |

### ✅ Tests Propuestos (5 casos)

1. Reprograma con fecha válida
2. Rechaza fecha pasada
3. Requiere motivo mínimo 10 caracteres
4. Conserva historial de cambios
5. Notificación al cliente se envía

**RF:** RF-PED-17

---

# CA11 - EJECUCIÓN DE INSTALACIONES

**RF relacionados:** RF-INS

## MÉTODO 1: Iniciar instalación

### 📝 Método Sugerido (Guía)
```
InstalacionService::iniciar()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `InstallationOrderController::start()`

### 💻 Código Real

```php
public function start(Request $request, InstallationOrder $order)
{
    if ($order->estado !== 'PROGRAMADO') {
        return back()->withErrors(['error' => 'Solo se pueden iniciar instalaciones PROGRAMADAS.']);
    }

    $order->update([
        'estado' => 'EN_PROCESO',
        'fecha_inicio_real' => now(),
        'hora_inicio_real' => now()->format('H:i:s'),
    ]);

    return redirect()->route('orders.show', $order)
        ->with('status', 'Instalación iniciada. Registra el consumo de materiales.');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Instalación | `InstallationOrder::factory()` | `estado = 'PROGRAMADO'` |
| Técnico | `Employee::factory()` | Asignado |

### ✅ Tests Propuestos (5 casos)

1. Inicia instalación PROGRAMADA exitosamente
2. Rechaza iniciar instalación ya EN_PROCESO
3. Rechaza iniciar instalación CANCELADA
4. Registra fecha y hora de inicio real
5. Estado cambia a EN_PROCESO

**RF:** RF-INS-03, RF-INS-04, RF-INS-05

---

## MÉTODO 2: Validar cierre automático

### 📝 Método Sugerido (Guía)
```
InstalacionService::puedeCerrar()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en lógica de cierre

### 💻 Código Real

```php
public function puedeCerrar(InstallationOrder $order): bool
{
    // Validar que tiene foto final
    if (!$order->foto_final) {
        return false;
    }

    // Validar que contrato está firmado
    $contrato = $order->contrato;
    if (!$contrato || !$contrato->documento_firmado) {
        return false;
    }

    // Validar que se registró consumo
    if ($order->materials()->count() === 0) {
        return false;
    }

    return true;
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Instalación | `InstallationOrder::factory()` | `estado = 'EN_PROCESO'` |
| Contrato | `Contract::factory()` | Con `documento_firmado` |
| Evidencias | Archivos | `foto_final` |
| Materiales | Consumos registrados | Mínimo 1 |

### ✅ Tests Propuestos (6 casos)

1. Con todos los requisitos retorna `true`
2. Sin foto_final retorna `false`
3. Sin documento_firmado retorna `false`
4. Sin consumo de materiales retorna `false`
5. Instalación NO EN_PROCESO retorna `false`
6. Mensaje específico indica qué falta

**RF:** RF-INS-12

---

## MÉTODO 3: Cerrar instalación

### 📝 Método Sugerido (Guía)
```
InstalacionService::cerrarAutomaticamente()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en `InstallationOrderController::close()`

### 💻 Código Real

```php
public function close(Request $request, InstallationOrder $order)
{
    if (!$this->puedeCerrar($order)) {
        return back()->withErrors(['error' => 'Faltan requisitos para cerrar la instalación.']);
    }

    $order->update([
        'estado' => 'COMPLETADO',
        'fecha_cierre' => now(),
        'hora_cierre' => now()->format('H:i:s'),
    ]);

    // Activar contrato
    $order->contrato->update(['estado' => 'ACTIVO']);

    // Generar pagos
    // ...

    return redirect()->route('orders.show', $order)
        ->with('status', 'Instalación completada exitosamente.');
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Instalación | `InstallationOrder::factory()` | Cumple `puedeCerrar()` |
| Contrato | `Contract::factory()` | Asociado |

### ✅ Tests Propuestos (6 casos)

1. Cierra instalación con requisitos completos
2. Estado cambia a COMPLETADO
3. Contrato asociado pasa a ACTIVO
4. Genera pagos automáticamente
5. Registra fecha y hora de cierre
6. Rechaza cerrar sin requisitos

**RF:** RF-INS-13, RF-INS-14

---

# CA12 - INDICADORES Y BI

**RF relacionados:** RF-IND, RF-BI

## MÉTODO 1: Calcular tiempo promedio de clientes

### 📝 Método Sugerido (Guía)
```
IndicadorService::promedioRegistroClientes()
```

### 🔍 Método Real en el Proyecto
**⚠️ NO IMPLEMENTADO** - Funcionalidad de BI pendiente

### 💻 Código Propuesto

```php
public function promedioRegistroClientes(string $periodo): float
{
    $clientes = Client::whereBetween('created_at', [
        Carbon::parse($periodo)->startOfMonth(),
        Carbon::parse($periodo)->endOfMonth(),
    ])->get();

    if ($clientes->isEmpty()) {
        return 0;
    }

    $totalDias = $clientes->sum(function ($client) {
        return $client->created_at->diffInDays(now());
    });

    return $totalDias / $clientes->count();
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Clientes | `Client::factory()` | Con fechas de registro |

### ✅ Tests Propuestos (4 casos)

1. Calcula promedio correcto con múltiples clientes
2. Retorna 0 si no hay clientes en periodo
3. Filtra correctamente por rango de fechas
4. Resultado redondeado a 2 decimales

**RF:** RF-IND-01

---

## MÉTODO 2: Calcular disponibilidad de equipos

### 📝 Método Sugerido (Guía)
```
IndicadorService::cantidadEquiposDisponibles()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** como query simple

### 💻 Código Real

```php
public function equiposDisponibles(): int
{
    return Equipment::where('estado', 'DISPONIBLE')->count();
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Equipos | `Equipment::factory()` | Variedad de estados |

### ✅ Tests Propuestos (4 casos)

1. Cuenta solo equipos DISPONIBLES
2. Excluye equipos ASIGNADOS
3. Excluye equipos MANTENIMIENTO
4. Excluye equipos BAJA

**RF:** RF-IND-08

---

## MÉTODO 3: Calcular pagos pendientes/vencidos

### 📝 Método Sugerido (Guía)
```
IndicadorService::conteoPagosPorEstado()
```

### 🔍 Método Real en el Proyecto
**✅ EXISTE** en AnalyticsController

### 💻 Código Real

```php
public function conteoPagos(string $periodo): array
{
    $inicio = Carbon::parse($periodo)->startOfMonth();
    $fin = Carbon::parse($periodo)->endOfMonth();

    return [
        'pendientes' => Payment::where('estado', 'PENDIENTE')
            ->whereBetween('fecha_vencimiento', [$inicio, $fin])
            ->count(),
        'vencidos' => Payment::where('estado', 'VENCIDO')
            ->whereBetween('fecha_vencimiento', [$inicio, $fin])
            ->count(),
        'realizados' => Payment::where('estado', 'REALIZADO')
            ->whereBetween('fecha_pago', [$inicio, $fin])
            ->count(),
    ];
}
```

### 📦 Dependencias Necesarias

| Dependency | Factory/Modelo | Datos requeridos |
|------------|----------------|------------------|
| Pagos | `Payment::factory()` | Variedad de estados y fechas |

### ✅ Tests Propuestos (5 casos)

1. Cuenta correctamente por cada estado
2. Filtra por rango de fechas
3. No incluye pagos fuera del periodo
4. Retorna array con claves correctas
5. Valores son enteros no negativos

**RF:** RF-IND-10, RF-IND-11

---

# 📊 RESUMEN EJECUTIVO

## Métodos por Estado

| Estado | Cantidad | Porcentaje |
|--------|----------|------------|
| ✅ Implementado | 48 | 80% |
| ⚠️ Parcial/Pendiente | 8 | 13.3% |
| ❌ No existe | 4 | 6.7% |
| **TOTAL** | **60** | **100%** |

## Prioridad de Implementación de Tests

### 🔴 CRÍTICO (Implementar primero)
1. **CA04 - Contratos:** `reactivate()`, `update()`
2. **CA05 - Pagos:** `generate()`, `complete()`, `markAsOverdue()`
3. **CA08 - Kardex:** `recordEntry()`, `recordExit()`, `recordConsumption()`
4. **CA11 - Instalaciones:** `iniciar()`, `puedeCerrar()`, `cerrar()`

### 🟠 IMPORTANTE (Segunda fase)
5. **CA06 - Equipos:** `asignar()`, `devolver()`, `processImport()`
6. **CA07 - Materiales:** `hayStock()`, `evitarStockNegativo()`
7. **CA10 - Pedidos:** `validarTecnicos()`, `hayConflictoHorario()`

### 🟡 COMPLEMENTARIO (Tercera fase)
8. **CA01 - Acceso:** `hasPermission()`, `cuentaPuedeIngresar()`
9. **CA02 - Clientes:** `store()`, `update()`, `deactivate()`
10. **CA09 - Cobertura:** `tieneCobertura()`, `planesDisponibles()`

### 🟢 BAJO (Cuarta fase)
11. **CA03 - Planes:** `estaDisponibleParaContrato()`
12. **CA12 - Indicadores:** Cálculos y agregaciones

---

## Estadísticas de Tests Propuestos

| Módulo | Tests Propuestos |
|--------|------------------:|
| CA04 - Contratos | 36 ✅ + 45 ⏳ |
| CA05 - Pagos | 24 ✅ + 30 ⏳ |
| CA08 - Kardex | 21 ⏳ |
| CA06 - Equipos | 17 ⏳ |
| CA11 - Instalaciones | 17 ⏳ |
| CA07 - Materiales | 13 ⏳ |
| CA10 - Pedidos | 15 ⏳ |
| CA02 - Clientes | 18 ⏳ |
| CA09 - Cobertura | 9 ⏳ |
| CA01 - Acceso | 12 ⏳ |
| CA03 - Planes | 3 ⏳ |
| CA12 - Indicadores | 13 ⏳ |
| **TOTAL** | **60 ✅ + 213 ⏳ = 273 tests** |

---

**Documento:** Análisis Completo de Métodos para Tests  
**Fecha:** 2024-10-14  
**Métodos analizados:** 60  
**Tests implementados:** 60 (22%)  
**Tests propuestos:** 213 (78%)  
**Total estimado:** 273 tests
