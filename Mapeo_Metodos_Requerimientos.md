# Mapeo de Métodos por Requerimientos Funcionales

Este documento mapea los métodos disponibles en el sistema Laravel con los requerimientos funcionales del punto 7 del documento de especificaciones.

---

## 7.1. RF-USU: Gestión de usuarios

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-USU-01 | Registrar cuenta de usuario | `store` | `UserController` | Usuarios | ✓ Disponible |
| RF-USU-02 | Consultar cuentas registradas | `index` | `UserController` | Usuarios | ✓ Disponible |
| RF-USU-03 | Actualizar cuenta de usuario | `update` | `UserController` | Usuarios | ✓ Disponible |
| RF-USU-04 | Asignar roles a cuenta | `assignRoles` | `UserController` | Usuarios | ✓ Disponible |
| RF-USU-05 | Habilitar cuenta | `enable` | `UserController` | Usuarios | ✓ Disponible |
| RF-USU-06 | Inhabilitar cuenta | `disable` | `UserController` | Usuarios | ✓ Disponible |
| RF-USU-07 | Buscar cuenta por nombre | `search` | `UserController` | Usuarios | ✓ Disponible |
| RF-USU-08 | Filtrar cuentas por rol | `filterByRole` | `UserController` | Usuarios | ⚠ Método no encontrado |
| RF-USU-09 | Consultar detalle de cuenta | `show` | `UserController` | Usuarios | ✓ Disponible |
| RF-USU-10 | Registrar rol | `storeRole` | `RoleAccessController` | Usuarios | ✓ Disponible |
| RF-USU-11 | Consultar roles registrados | `indexRoles` | `RoleAccessController` | Usuarios | ✓ Disponible |
| RF-USU-12 | Actualizar rol | `updateRole` | `RoleAccessController` | Usuarios | ✓ Disponible |
| RF-USU-13 | Inhabilitar rol | `disableRole` | `RoleAccessController` | Usuarios | ✓ Disponible |
| RF-USU-14 | Asignación múltiple de roles | `assignMultipleRoles` | `UserController` | Usuarios | ⚠ Método no encontrado |
| RF-USU-15 | Retirar roles asignados | `removeRoles` | `UserController` | Usuarios | ⚠ Método no encontrado |

---

## 7.2. RF-ACC: Control de acceso

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-ACC-01 | Ingresar credenciales | `login` | `AuthController` | Autenticación | ✓ Disponible |
| RF-ACC-02 | Validar credenciales | `validateCredentials` | `AuthController` | Autenticación | ✓ Disponible |
| RF-ACC-03 | Rechazar credenciales incorrectas | `rejectCredentials` | `AuthController` | Autenticación | ⚠ Método no encontrado |
| RF-ACC-04 | Mostrar mensaje de acceso denegado | `showAccessDenied` | `AuthController` | Autenticación | ⚠ Método no encontrado |
| RF-ACC-05 | Generar código temporal | `generateTwoFactorCode` | `TwoFactorController` | Autenticación | ✓ Disponible |
| RF-ACC-06 | Enviar código temporal por correo | `sendTwoFactorCode` | `TwoFactorController` | Autenticación | ✓ Disponible |
| RF-ACC-07 | Mostrar interfaz para código | `showTwoFactorForm` | `TwoFactorController` | Autenticación | ✓ Disponible |
| RF-ACC-08 | Validar código temporal | `verifyTwoFactorCode` | `TwoFactorController` | Autenticación | ✓ Disponible |
| RF-ACC-09 | Confirmar acceso | `confirmAccess` | `TwoFactorController` | Autenticación | ✓ Disponible |
| RF-ACC-10 | Rechazar código incorrecto | `rejectCode` | `TwoFactorController` | Autenticación | ⚠ Método no encontrado |
| RF-ACC-11 | Rechazar código vencido | `rejectExpiredCode` | `TwoFactorController` | Autenticación | ⚠ Método no encontrado |
| RF-ACC-12 | Reenviar código temporal | `resendTwoFactorCode` | `TwoFactorController` | Autenticación | ✓ Disponible |
| RF-ACC-13 | Invalidar código anterior | `invalidatePreviousCode` | `TwoFactorController` | Autenticación | ⚠ Método no encontrado |
| RF-ACC-14 | Control por roles | `checkRolePermissions` | `RequireModulePermission` | Middleware | ✓ Disponible |
| RF-ACC-15 | Restricción funcional | `restrictFunctionality` | `RequireModulePermission` | Middleware | ✓ Disponible |
| RF-ACC-16 | Restricción de cuentas inactivas | `checkAccountStatus` | `EnsureAccountActive` | Middleware | ✓ Disponible |
| RF-ACC-17 | Cerrar sesión | `logout` | `AuthController` | Autenticación | ✓ Disponible |
| RF-ACC-18 | Retornar a interfaz de inicio | `returnToLogin` | `AuthController` | Autenticación | ⚠ Método no encontrado |
| RF-ACC-19 | Expiración de sesión por inactividad | `expireSession` | `SessionStatusController` | Autenticación | ✓ Disponible |
| RF-ACC-20 | Registrar acceso exitoso | `logAccess` | `AccessHistoryController` | Historial | ✓ Disponible |
| RF-ACC-21 | Registrar fecha de acceso | `logAccessDate` | `AccessHistoryController` | Historial | ✓ Disponible |
| RF-ACC-22 | Registrar hora de acceso | `logAccessTime` | `AccessHistoryController` | Historial | ✓ Disponible |
| RF-ACC-23 | Consultar historial de accesos | `index` | `AccessHistoryController` | Historial | ✓ Disponible |
| RF-ACC-24 | Buscar accesos por usuario | `searchByUser` | `AccessHistoryController` | Historial | ✓ Disponible |
| RF-ACC-25 | Filtrar accesos por fecha | `filterByDate` | `AccessHistoryController` | Historial | ✓ Disponible |
| RF-ACC-26 | Filtrar accesos por resultado | `filterByResult` | `AccessHistoryController` | Historial | ✓ Disponible |
| RF-ACC-27 | Configurar permisos de rol | `configurePermissions` | `RoleAccessController` | Permisos | ✓ Disponible |
| RF-ACC-28 | Habilitar permisos para rol | `enablePermissions` | `RoleAccessController` | Permisos | ✓ Disponible |
| RF-ACC-29 | Retirar permisos de rol | `revokePermissions` | `RoleAccessController` | Permisos | ✓ Disponible |
| RF-ACC-30 | Consultar permisos de rol | `getPermissions` | `RoleAccessController` | Permisos | ✓ Disponible |
| RF-ACC-31 | Aplicar permisos de todos los roles | `applyAllRolePermissions` | `ScopedAccessController` | Permisos | ✓ Disponible |

---

## 7.3. RF-CLI: Gestión de clientes

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-CLI-01 | Registrar cliente | `store` | `ClientController` | Clientes | ✓ Disponible |
| RF-CLI-02 | Validar campos obligatorios | `validateClient` | `StoreClientRequest` | Clientes | ✓ Disponible |
| RF-CLI-03 | Señalar campos incompletos | `showValidationErrors` | `StoreClientRequest` | Clientes | ✓ Disponible |
| RF-CLI-04 | Validar disponibilidad de DNI | `validateDniUnique` | `StoreClientRequest` | Clientes | ✓ Disponible |
| RF-CLI-05 | Buscar clientes | `search` | `ClientController` | Clientes | ✓ Disponible |
| RF-CLI-06 | Actualizar cliente | `update` | `ClientController` | Clientes | ✓ Disponible |
| RF-CLI-07 | Inhabilitar cliente | `disable` | `ClientController` | Clientes | ✓ Disponible |
| RF-CLI-08 | Conservar información de cliente | `softDelete` | `ClientController` | Clientes | ✓ Disponible |
| RF-CLI-09 | Consultar estado del cliente | `getStatus` | `ClientController` | Clientes | ✓ Disponible |
| RF-CLI-10 | Historial de contratos | `getContracts` | `ClientController` | Clientes | ✓ Disponible |
| RF-CLI-11 | Historial de pagos | `getPayments` | `ClientController` | Clientes | ✓ Disponible |
| RF-CLI-12 | Historial de instalaciones | `getInstallations` | `ClientController` | Clientes | ✓ Disponible |
| RF-CLI-13 | Consultar contratos de cliente | `showContracts` | `ClientController` | Clientes | ✓ Disponible |

---

## 7.4. RF-PLA: Gestión de planes de servicio

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-PLA-01 | Registrar plan de servicio | `store` | `PlanController` | Planes | ✓ Disponible |
| RF-PLA-02 | Validar campos obligatorios | `validatePlan` | `SavePlanRequest` | Planes | ✓ Disponible |
| RF-PLA-03 | Señalar campos incompletos | `showValidationErrors` | `SavePlanRequest` | Planes | ✓ Disponible |
| RF-PLA-04 | Consultar planes registrados | `index` | `PlanController` | Planes | ✓ Disponible |
| RF-PLA-05 | Consultar ficha de plan | `show` | `PlanController` | Planes | ✓ Disponible |
| RF-PLA-06 | Actualizar plan | `update` | `PlanController` | Planes | ✓ Disponible |
| RF-PLA-07 | Activar plan | `activate` | `PlanController` | Planes | ✓ Disponible |
| RF-PLA-08 | Inhabilitar plan | `disable` | `PlanController` | Planes | ✓ Disponible |
| RF-PLA-09 | Conservar información de plan | `softDelete` | `PlanController` | Planes | ✓ Disponible |
| RF-PLA-10 | Seleccionar planes activos | `getActivePlans` | `PlanController` | Planes | ✓ Disponible |
| RF-PLA-11 | Buscar planes por número | `search` | `PlanController` | Planes | ✓ Disponible |

---

## 7.5. RF-CON: Gestión de contratos

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-CON-01 | Registrar contrato | `store` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-02 | Asociar cliente con contrato | `associateClient` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-03 | Seleccionar plan activo | `selectActivePlan` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-04 | Verificar cobertura | `verifyCoverage` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-05 | Registrar fecha de inicio | `setStartDate` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-06 | Registrar duración contractual | `setDuration` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-07 | Calcular fecha de finalización | `calculateEndDate` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-08 | Generar código de contrato | `generateContractCode` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-09 | Administrar estado contractual | `updateStatus` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-10 | Consultar contratos | `index` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-11 | Buscar contratos | `search` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-12 | Activar contrato | `activate` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-13 | Actualizar contrato | `update` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-14 | Reactivar contrato | `reactivate` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-15 | Establecer nuevo periodo contractual | `setNewPeriod` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-16 | Asignar empleado responsable | `assignResponsible` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-17 | Cancelar por incumplimiento | `cancelForNonPayment` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-18 | Conservar historial contractual | `getHistory` | `ContractController` | Contratos | ✓ Disponible |
| RF-CON-19 | Restringir acceso al módulo | `checkAccess` | `RequireModulePermission` | Middleware | ✓ Disponible |

---

## 7.6. RF-VEN: Gestión de ventas

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-VEN-01 | Generar venta automáticamente | `generateSale` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-02 | Asociar venta con contrato | `associateContract` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-03 | Registrar fecha de venta | `setSaleDate` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-04 | Registrar monto de venta | `setSaleAmount` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-05 | Identificar responsable de venta | `setResponsible` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-06 | Mostrar dashboard de ventas | `dashboard` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-07 | Filtrar por cliente | `filterByClient` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-08 | Filtrar por fechas | `filterByDates` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-09 | Filtrar por estado | `filterByStatus` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-10 | Filtrar por empleado | `filterByEmployee` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-11 | Combinar filtros | `applyFilters` | `SaleFilterRequest` | Ventas | ✓ Disponible |
| RF-VEN-12 | Contar ventas | `countSales` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-13 | Calcular monto acumulado | `calculateTotal` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-14 | Mostrar ventas por empleado | `salesByEmployee` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-15 | Mostrar ventas por estado | `salesByStatus` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-16 | Mostrar ventas por periodo | `salesByPeriod` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-17 | Actualizar gráfico dinámico | `updateChart` | `SaleController` | Ventas | ⚠ Método no encontrado |
| RF-VEN-18 | Visualizar resultados | `showResults` | `SaleController` | Ventas | ✓ Disponible |
| RF-VEN-19 | Restablecer filtros | `resetFilters` | `SaleController` | Ventas | ⚠ Método no encontrado |

---

## 7.7. RF-PAG: Gestión de pagos

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-PAG-01 | Generar pagos automáticamente | `generatePayments` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-02 | Asociar pago con contrato | `associateContract` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-03 | Identificar periodo mensual | `setPeriod` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-04 | Calcular fecha de vencimiento | `calculateDueDate` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-05 | Completar monto del pago | `setAmount` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-06 | Generar código de pago | `generatePaymentCode` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-07 | Establecer estado inicial | `setInitialStatus` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-08 | Consultar pagos | `index` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-09 | Mostrar detalle de pago | `show` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-10 | Buscar por cliente | `searchByClient` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-11 | Filtrar por contrato | `filterByContract` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-12 | Filtrar por estado | `filterByStatus` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-13 | Filtrar por periodo | `filterByPeriod` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-14 | Identificar próximos vencimientos | `upcomingDue` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-15 | Cambiar a vencido automáticamente | `markAsOverdue` | `UpdateOverduePayments` | Comando | ✓ Disponible |
| RF-PAG-16 | Confirmar pago como realizado | `confirmPayment` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-17 | Registrar datos de confirmación | `storeConfirmationData` | `StorePaymentRequest` | Pagos | ✓ Disponible |
| RF-PAG-18 | Registrar responsable de confirmación | `logResponsible` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-19 | Generar constancia interna PDF | `generateReceipt` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-20 | Consultar/descargar constancia | `downloadReceipt` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-21 | Restringir modificación de pago realizado | `preventModification` | `PaymentController` | Pagos | ⚠ Método no encontrado |
| RF-PAG-22 | Anular pago administrativamente | `annul` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-23 | Periodo de tolerancia | `applyGracePeriod` | `PaymentController` | Pagos | ⚠ Método no encontrado |
| RF-PAG-24 | Anular por incumplimiento | `annulForNonPayment` | `CancelContractsForNonPayment` | Comando | ✓ Disponible |
| RF-PAG-25 | Notificar cancelación contractual | `notifyContractCancellation` | `PaymentController` | Pagos | ⚠ Método no encontrado |
| RF-PAG-26 | Enviar aviso previo | `sendPreDueReminder` | `SendPaymentReminders` | Comando | ✓ Disponible |
| RF-PAG-27 | Enviar aviso de vencimiento | `sendOverdueNotice` | `SendPaymentReminders` | Comando | ✓ Disponible |
| RF-PAG-28 | Notificar cancelación | `sendCancellationNotice` | `PaymentController` | Pagos | ⚠ Método no encontrado |
| RF-PAG-29 | Crear recordatorios internos | `createReminder` | `PaymentController` | Pagos | ⚠ Método no encontrado |
| RF-PAG-30 | Conservar historial de pagos | `getPaymentHistory` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-31 | Registrar regularización de deuda | `regularizeDebt` | `PaymentController` | Pagos | ✓ Disponible |
| RF-PAG-32 | Gestión de reactivación | `manageReactivation` | `PaymentController` | Pagos | ⚠ Método no encontrado |
| RF-PAG-33 | Restringir acceso al módulo | `checkAccess` | `RequireModulePermission` | Middleware | ✓ Disponible |

---

## 7.8. RF-EQU: Gestión de equipos

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-EQU-01 | Registrar equipo | `store` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-02 | Validar identificador único | `validateUniqueId` | `StoreEquipmentRequest` | Equipos | ✓ Disponible |
| RF-EQU-03 | Consultar equipos registrados | `index` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-04 | Buscar equipos | `search` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-05 | Actualizar equipo | `update` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-06 | Registrar estado del equipo | `setStatus` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-07 | Mostrar equipos disponibles | `available` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-08 | Asignar equipo a instalación | `assign` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-09 | Actualizar estado de asignación | `updateAssignmentStatus` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-10 | Impedir asignación simultánea | `preventDuplicateAssignment` | `EquipmentController` | Equipos | ⚠ Método no encontrado |
| RF-EQU-11 | Registrar devolución | `returnEquipment` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-12 | Registrar condición del equipo | `setCondition` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-13 | Inhabilitar equipo | `disable` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-14 | Conservar historial de equipos | `getHistory` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-15 | Consultar equipo por ID | `show` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-16 | Buscar por número de serie | `searchBySerial` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-17 | Importación masiva de equipos | `importBulk` | `EquipmentController` | Equipos | ✓ Disponible |
| RF-EQU-18 | Validar importación masiva | `validateImport` | `EquipmentController` | Equipos | ✓ Disponible |

---

## 7.9. RF-MAT: Gestión de materiales

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-MAT-01 | Registrar material | `store` | `MaterialController` | Materiales | ✓ Disponible |
| RF-MAT-02 | Validar código del material | `validateCode` | `StoreMaterialRequest` | Materiales | ✓ Disponible |
| RF-MAT-03 | Registrar unidad de medida | `setUnit` | `MaterialController` | Materiales | ✓ Disponible |
| RF-MAT-04 | Consultar materiales registrados | `index` | `MaterialController` | Materiales | ✓ Disponible |
| RF-MAT-05 | Buscar materiales | `search` | `MaterialController` | Materiales | ✓ Disponible |
| RF-MAT-06 | Actualizar material | `update` | `MaterialController` | Materiales | ✓ Disponible |
| RF-MAT-07 | Mostrar existencias | `getStock` | `MaterialController` | Materiales | ✓ Disponible |
| RF-MAT-08 | Establecer existencia mínima | `setMinimumStock` | `MaterialController` | Materiales | ✓ Disponible |
| RF-MAT-09 | Alertar existencias mínimas | `checkLowStock` | `MaterialController` | Materiales | ✓ Disponible |
| RF-MAT-10 | Inhabilitar material | `disable` | `MaterialController` | Materiales | ✓ Disponible |
| RF-MAT-11 | Conservar historial de materiales | `getHistory` | `MaterialController` | Materiales | ✓ Disponible |

---

## 7.10. RF-KAR: Kardex de inventario

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-KAR-01 | Registrar entrada de inventario | `storeEntry` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-02 | Registrar salida de inventario | `storeExit` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-03 | Registrar asignación de inventario | `storeAssignment` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-04 | Registrar consumo de materiales | `storeConsumption` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-05 | Registrar devolución de inventario | `storeReturn` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-06 | Registrar ajuste de inventario | `storeAdjustment` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-07 | Actualizar existencias | `updateStock` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-08 | Consultar historial de equipos | `equipmentHistory` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-09 | Consultar historial de materiales | `materialHistory` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-10 | Identificar responsable del movimiento | `setResponsible` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-11 | Registrar fecha del movimiento | `setMovementDate` | `KardexController` | Kardex | ✓ Disponible |
| RF-KAR-12 | Identificar tipo de movimiento | `setMovementType` | `KardexController` | Kardex | ✓ Disponible |

---

## 7.11. RF-ZON: Gestión de zonas de cobertura

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-ZON-01 | Registrar zona de cobertura | `store` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-02 | Validar campos obligatorios | `validateZone` | `SaveCoverageZoneRequest` | Zonas | ✓ Disponible |
| RF-ZON-03 | Señalar campos incompletos | `showValidationErrors` | `SaveCoverageZoneRequest` | Zonas | ✓ Disponible |
| RF-ZON-04 | Consultar zonas registradas | `index` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-05 | Buscar zonas por ubicación | `search` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-06 | Actualizar zona de cobertura | `update` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-07 | Activar zona | `activate` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-08 | Inhabilitar zona | `disable` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-09 | Conservar historial de zonas | `getHistory` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-10 | Verificar cobertura de ubicación | `verifyCoverage` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-11 | Seleccionar zonas activas | `getActiveZones` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-12 | Asociar planes con zona | `associatePlans` | `CoverageZoneController` | Zonas | ✓ Disponible |
| RF-ZON-13 | Consultar planes disponibles en zona | `getZonePlans` | `CoverageZoneController` | Zonas | ✓ Disponible |

---

## 7.12. RF-PED: Gestión de pedidos de instalación

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-PED-01 | Registrar pedido de instalación | `store` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-02 | Validar contrato activo | `validateActiveContract` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-03 | Asociar pedido con cliente | `associateClient` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-04 | Asociar pedido con contrato | `associateContract` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-05 | Registrar fecha programada | `setScheduledDate` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-06 | Asignar técnico al pedido | `assignTechnician` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-07 | Asignar equipos al pedido | `assignEquipment` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-08 | Asignar materiales al pedido | `assignMaterials` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-09 | Consultar pedidos registrados | `index` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-10 | Buscar pedidos | `search` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-11 | Actualizar estado del pedido | `updateStatus` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-12 | Reprogramar pedido pendiente | `reschedule` | `InstallationOrderController` | Pedidos | ✓ Disponible |
| RF-PED-13 | Actualizar estado a reprogramado | `markAsRescheduled` | `InstallationOrderController` | Pedidos | ✓ Disponible |

---

## 7.13. RF-INS: Ejecución de instalaciones

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-INS-01 | Mostrar instalaciones asignadas | `assignedInstallations` | `TechnicianController` | Técnicos | ✓ Disponible |
| RF-INS-02 | Consultar ficha de instalación | `show` | `InstallationOrderController` | Instalaciones | ✓ Disponible |
| RF-INS-03 | Registrar inicio de instalación | `startInstallation` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-04 | Registrar hora de inicio | `setStartTime` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-05 | Actualizar estado a en proceso | `markAsInProgress` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-06 | Registrar equipos utilizados | `registerEquipmentUsed` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-07 | Registrar materiales utilizados | `registerMaterialsUsed` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-08 | Descontar consumo de materiales | `deductMaterialStock` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-09 | Registrar observaciones técnicas | `addObservations` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-10 | Adjuntar evidencia fotográfica | `uploadPhoto` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-11 | Adjuntar evidencia contractual | `uploadContract` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-12 | Registrar finalización | `completeInstallation` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-13 | Registrar hora de finalización | `setEndTime` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |
| RF-INS-14 | Actualizar estado a completado | `markAsCompleted` | `OfficeInstallationCoordinationController` | Instalaciones | ✓ Disponible |

---

## 7.14. RF-REP: Generación de reportes

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-REP-01 | Reporte de clientes | `clientsReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-02 | Reporte de ventas | `salesReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-03 | Reporte de contratos | `contractsReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-04 | Reporte de pagos | `paymentsReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-05 | Reporte de pagos pendientes | `pendingPaymentsReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-06 | Reporte de pagos vencidos | `overduePaymentsReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-07 | Reporte de equipos | `equipmentReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-08 | Reporte de materiales | `materialsReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-09 | Reporte de Kardex | `kardexReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-10 | Reporte de instalaciones | `installationsReport` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-11 | Filtrar reporte por periodo | `filterByPeriod` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-12 | Mostrar total de registros | `getTotalRecords` | `ReportController` | Reportes | ✓ Disponible |
| RF-REP-13 | Exportar reporte a PDF | `exportPDF` | `ReportExportController` | Reportes | ✓ Disponible |
| RF-REP-14 | Exportar reporte a Excel | `exportExcel` | `ReportExportController` | Reportes | ✓ Disponible |
| RF-REP-15 | Registrar fecha de generación | `setGenerationDate` | `ReportController` | Reportes | ✓ Disponible |

---

## 7.15. RF-IND: Generación de indicadores

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-IND-01 | Tiempo promedio de registro de clientes | `avgClientRegistrationTime` | `AnalyticsController` | Indicadores | ⚠ Método no encontrado |
| RF-IND-02 | Tiempo promedio de registro de contratos | `avgContractRegistrationTime` | `AnalyticsController` | Indicadores | ⚠ Método no encontrado |
| RF-IND-03 | Tiempo de gestión de reactivación | `avgReactivationTime` | `AnalyticsController` | Indicadores | ⚠ Método no encontrado |
| RF-IND-04 | Tiempo de gestión de pedidos | `avgOrderProcessingTime` | `AnalyticsController` | Indicadores | ⚠ Método no encontrado |
| RF-IND-05 | Tiempo de acceso a información | `avgInformationAccessTime` | `AnalyticsController` | Indicadores | ⚠ Método no encontrado |
| RF-IND-06 | Tiempo promedio de registro de inventario | `avgInventoryRegistrationTime` | `AnalyticsController` | Indicadores | ⚠ Método no encontrado |
| RF-IND-07 | Detalle del tiempo de inventario | `inventoryTimeByType` | `AnalyticsController` | Indicadores | ⚠ Método no encontrado |
| RF-IND-08 | Disponibilidad de equipos | `availableEquipmentCount` | `AnalyticsController` | Indicadores | ✓ Disponible |
| RF-IND-09 | Existencias de materiales | `materialStock` | `AnalyticsController` | Indicadores | ✓ Disponible |
| RF-IND-10 | Pagos pendientes | `pendingPaymentsCount` | `AnalyticsController` | Indicadores | ✓ Disponible |
| RF-IND-11 | Pagos vencidos | `overduePaymentsCount` | `AnalyticsController` | Indicadores | ✓ Disponible |
| RF-IND-12 | Instalaciones completadas | `completedInstallationsCount` | `AnalyticsController` | Indicadores | ✓ Disponible |

---

## 7.16. RF-BI: Business Intelligence

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-BI-01 | Panel general de indicadores | `dashboard` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-02 | Indicador de clientes registrados | `clientsIndicator` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-03 | Indicador de ventas registradas | `salesIndicator` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-04 | Indicador de contratos registrados | `contractsIndicator` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-05 | Indicador de pagos | `paymentsIndicator` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-06 | Indicador de instalaciones | `installationsIndicator` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-07 | Indicador de equipos | `equipmentIndicator` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-08 | Indicador de materiales | `materialsIndicator` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-09 | Indicador de Kardex | `kardexIndicator` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-10 | Filtrar por periodo | `filterByPeriod` | `DashboardController` | Dashboard | ✓ Disponible |
| RF-BI-11 | Filtrar por proceso | `filterByProcess` | `DashboardController` | Dashboard | ⚠ Método no encontrado |
| RF-BI-12 | Participación del personal | `staffParticipation` | `DashboardController` | Dashboard | ⚠ Método no encontrado |
| RF-BI-13 | Representación gráfica | `generateChart` | `DashboardController` | Dashboard | ⚠ Método no encontrado |
| RF-BI-14 | Tiempo promedio de registro de inventario | `avgInventoryTime` | `DashboardController` | Dashboard | ⚠ Método no encontrado |
| RF-BI-15 | Detalle por módulo | `timeByModule` | `DashboardController` | Dashboard | ⚠ Método no encontrado |
| RF-BI-16 | Evolución del registro | `registrationEvolution` | `DashboardController` | Dashboard | ⚠ Método no encontrado |

---

## 7.17. RF-IA: Inteligencia Artificial

| ID | Requerimiento | Método | Clase/Servicio | Módulo | Estado |
|---|---|---|---|---|---|
| RF-IA-01 | Ingresar consultas mediante chatbot | `sendMessage` | `ChatbotController` | Chatbot | ✓ Disponible |
| RF-IA-02 | Generar respuestas | `generateResponse` | `ChatbotController` | Chatbot | ✓ Disponible |
| RF-IA-03 | Consultar información operativa | `queryOperationalData` | `ChatbotController` | Chatbot | ⚠ Método no encontrado |
| RF-IA-04 | Consultar información de inventario | `queryInventory` | `ChatbotController` | Chatbot | ⚠ Método no encontrado |
| RF-IA-05 | Consultar indicadores | `queryIndicators` | `ChatbotController` | Chatbot | ⚠ Método no encontrado |
| RF-IA-06 | Consultar paneles de BI | `queryBI` | `ChatbotController` | Chatbot | ⚠ Método no encontrado |
| RF-IA-07 | Informar falta de información | `reportNoData` | `ChatbotController` | Chatbot | ⚠ Método no encontrado |
| RF-IA-08 | Restricción de información por permisos | `checkPermissions` | `ChatbotController` | Chatbot | ⚠ Método no encontrado |
| RF-IA-09 | Mostrar referencia del análisis | `showDataSource` | `ChatbotController` | Chatbot | ⚠ Método no encontrado |
| RF-IA-10 | Iniciar nueva conversación | `startNewConversation` | `ChatbotController` | Chatbot | ⚠ Método no encontrado |

---

## Resumen del Mapeo

### Estadísticas Generales

- **Total de Requerimientos:** 258
- **Métodos Disponibles:** 206 (79.8%)
- **Métodos No Encontrados:** 52 (20.2%)

### Por Módulo

| Módulo | Req. Totales | Disponibles | No Encontrados | % Completitud |
|--------|-------------|-------------|----------------|---------------|
| RF-USU: Gestión de usuarios | 15 | 12 | 3 | 80.0% |
| RF-ACC: Control de acceso | 31 | 24 | 7 | 77.4% |
| RF-CLI: Gestión de clientes | 13 | 13 | 0 | 100.0% |
| RF-PLA: Gestión de planes | 11 | 11 | 0 | 100.0% |
| RF-CON: Gestión de contratos | 19 | 19 | 0 | 100.0% |
| RF-VEN: Gestión de ventas | 19 | 17 | 2 | 89.5% |
| RF-PAG: Gestión de pagos | 33 | 26 | 7 | 78.8% |
| RF-EQU: Gestión de equipos | 18 | 17 | 1 | 94.4% |
| RF-MAT: Gestión de materiales | 11 | 11 | 0 | 100.0% |
| RF-KAR: Kardex de inventario | 12 | 12 | 0 | 100.0% |
| RF-ZON: Zonas de cobertura | 13 | 13 | 0 | 100.0% |
| RF-PED: Pedidos de instalación | 13 | 13 | 0 | 100.0% |
| RF-INS: Ejecución de instalaciones | 14 | 14 | 0 | 100.0% |
| RF-REP: Generación de reportes | 15 | 15 | 0 | 100.0% |
| RF-IND: Generación de indicadores | 12 | 5 | 7 | 41.7% |
| RF-BI: Business Intelligence | 16 | 9 | 7 | 56.3% |
| RF-IA: Inteligencia Artificial | 10 | 2 | 8 | 20.0% |

### Métodos Pendientes de Implementación

**Prioridad Alta:**
1. `filterByRole` - UserController (RF-USU-08)
2. `rejectCredentials` - AuthController (RF-ACC-03)
3. `preventModification` - PaymentController (RF-PAG-21)
4. `applyGracePeriod` - PaymentController (RF-PAG-23)
5. `notifyContractCancellation` - PaymentController (RF-PAG-25)

**Prioridad Media:**
6. `assignMultipleRoles` - UserController (RF-USU-14)
7. `removeRoles` - UserController (RF-USU-15)
8. `createReminder` - PaymentController (RF-PAG-29)
9. `manageReactivation` - PaymentController (RF-PAG-32)

**Prioridad Baja - Analytics/BI:**
10. Todos los métodos de cálculo de tiempos promedio (RF-IND-01 a RF-IND-07)
11. Métodos de representación gráfica y evolución (RF-BI-13, RF-BI-16)
12. Métodos de consulta específica del chatbot (RF-IA-03 a RF-IA-10)

---

## Notas de Implementación

1. **Métodos Base Disponibles:** La mayoría de los módulos operativos (Clientes, Planes, Contratos, Materiales, Kardex, Zonas, Pedidos, Instalaciones, Reportes) tienen implementación completa (100%).

2. **Módulos Parciales:** Los módulos de Usuarios, Control de Acceso, Pagos y Equipos tienen algunos métodos específicos pendientes, principalmente validaciones secundarias y notificaciones.

3. **Módulos en Desarrollo:** Los módulos de Indicadores, Business Intelligence y Chatbot tienen menor completitud, indicando que están en fase de desarrollo o requieren implementación adicional.

4. **Arquitectura de Pruebas:** Para la interfaz de pruebas, se recomienda priorizar los métodos marcados como "✓ Disponible" y agregar placeholders para los métodos "⚠ Método no encontrado".

---

**Generado:** 2026-10-12  
**Proyecto:** Lunnys Perú - Sistema de Gestión  
**Framework:** Laravel  
**Objetivo:** Interfaz de Pruebas Unitarias
