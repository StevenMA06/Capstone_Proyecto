<?php

use App\Http\Controllers\AccessHistoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CoverageZoneController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SessionStatusController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChatbotController;
use App\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:30,1')->name('login.store');
    Route::get('/access/code', [TwoFactorController::class, 'create'])->name('two-factor.create');
    Route::post('/access/code', [TwoFactorController::class, 'store'])->middleware('throttle:20,1')->name('two-factor.store');
    Route::post('/access/code/resend', [TwoFactorController::class, 'resend'])->middleware('throttle:5,1')->name('two-factor.resend');
    Route::post('/access/code/cancel', [TwoFactorController::class, 'cancel'])->name('two-factor.cancel');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/session/status', SessionStatusController::class)->withoutMiddleware(EnsureAccountActive::class)->name('session.status');
    Route::get('/access-history', AccessHistoryController::class)->name('access.index');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth'])->group(function (): void {
    Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
    Route::resource('users', UserController::class)->except('destroy');
});

Route::middleware(['auth'])->group(function (): void {
    Route::patch('/employees/{employee}/status', [EmployeeController::class, 'status'])->name('employees.status');
    Route::resource('employees', EmployeeController::class)->except('destroy');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/installations', [OperationController::class, 'installations'])->name('installations.index');
    Route::get('/equipment', [OperationController::class, 'equipment'])->name('equipment.index');
    
    // Chatbot: acceso y consultas según los permisos efectivos.
    Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot.index');
    Route::post('/chatbot/query', [ChatbotController::class, 'query'])->middleware('throttle:60,1')->name('chatbot.query');
    Route::post('/chatbot/new', [ChatbotController::class, 'newConversation'])->name('chatbot.new');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
    Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->whereNumber('contract')->name('contracts.show');
    Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])->whereNumber('contract')->name('contracts.edit');
    Route::put('/contracts/{contract}', [ContractController::class, 'update'])->whereNumber('contract')->name('contracts.update');
    Route::get('/contracts/{contract}/pdf', [ContractController::class, 'pdf'])->whereNumber('contract')->name('contracts.pdf');
    Route::get('/contracts/{contract}/field-evidence/{evidence}', [ContractController::class, 'evidence'])->whereNumber(['contract', 'evidence'])->name('contracts.evidence');
    Route::get('/contracts/{contract}/draft', [ContractController::class, 'draft'])->whereNumber('contract')->name('contracts.draft');

    // RF-CON-04: Reactivación de contratos cancelados
    Route::get('/contracts-cancelled', [ContractController::class, 'cancelled'])->name('contracts.cancelled');
    Route::get('/contracts/{contract}/reactivate', [ContractController::class, 'reactivateForm'])->whereNumber('contract')->name('contracts.reactivate.form');
    Route::post('/contracts/{contract}/reactivate', [ContractController::class, 'reactivate'])->whereNumber('contract')->name('contracts.reactivate');
});

Route::middleware(['auth'])->group(function (): void {
    Route::patch('/zones/{zone}/status', [CoverageZoneController::class, 'status'])->name('zones.status');
    Route::resource('zones', CoverageZoneController::class)->only(['create', 'store', 'edit', 'update']);
});
Route::middleware(['auth'])->group(function (): void {
    Route::resource('zones', CoverageZoneController::class)->only(['index', 'show']);
});

Route::middleware(['auth'])->group(function (): void {
    Route::patch('/plans/{plan}/status', [PlanController::class, 'status'])->name('plans.status');
    Route::resource('plans', PlanController::class)->only(['create', 'store', 'edit', 'update']);
});
Route::middleware(['auth'])->group(function (): void {
    Route::resource('plans', PlanController::class)->only(['index', 'show']);
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/technician', [TechnicianController::class, 'index'])->name('technician.index');
    Route::post('/technician/installations/{pedido}/start', [TechnicianController::class, 'start'])->whereNumber('pedido')->name('technician.start');
    Route::post('/technician/installations/{pedido}/finish', [TechnicianController::class, 'finish'])->whereNumber('pedido')->name('technician.finish');
    Route::post('/technician/installations/{pedido}/equipment', [TechnicianController::class, 'equipment'])->whereNumber('pedido')->name('technician.equipment');
    Route::post('/technician/installations/{pedido}/material', [TechnicianController::class, 'material'])->whereNumber('pedido')->name('technician.material');
    Route::post('/technician/installations/{pedido}/observation', [TechnicianController::class, 'observation'])->whereNumber('pedido')->name('technician.observation');
    Route::post('/technician/installations/{pedido}/contract-evidence', [TechnicianController::class, 'contractEvidence'])->whereNumber('pedido')->name('technician.contractEvidence');
    Route::get('/technician/installations/{pedido}/contract-evidence/{evidence}/preview', [TechnicianController::class, 'previewContract'])->whereNumber(['pedido', 'evidence'])->name('technician.contract.preview');
    Route::get('/technician/installations/{pedido}/contract-evidence/{evidence}', [TechnicianController::class, 'downloadContract'])->whereNumber(['pedido', 'evidence'])->name('technician.contract.download');
    Route::get('/technician/installations/{pedido}', [TechnicianController::class, 'show'])->whereNumber('pedido')->name('technician.show');
    Route::post('/technician/installations/{pedido}/evidence', [TechnicianController::class, 'store'])->whereNumber('pedido')->name('technician.evidence.store');
    Route::get('/technician/installations/{pedido}/evidence/{evidence}/preview', [TechnicianController::class, 'preview'])->whereNumber(['pedido', 'evidence'])->name('technician.evidence.preview');
    Route::get('/technician/installations/{pedido}/evidence/{evidence}', [TechnicianController::class, 'download'])->whereNumber(['pedido', 'evidence'])->name('technician.evidence.download');
});

Route::middleware(['auth'])->group(function (): void {
    Route::patch('/clients/{client}/deactivate', [ClientController::class, 'deactivate'])->name('clients.deactivate');
    Route::resource('clients', ClientController::class)->except('destroy');
});

Route::middleware(['auth'])->group(function (): void {
    Route::patch('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');
    Route::patch('/payments/{payment}/complete', [PaymentController::class, 'complete'])->name('payments.complete');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->whereNumber('payment')->name('payments.receipt');
    Route::post('/payments/{payment}/reminders', [PaymentController::class, 'createReminder'])->whereNumber('payment')->name('payments.reminders.create');
    Route::patch('/payments/{payment}/reminders/{reminder}', [PaymentController::class, 'attendReminder'])->whereNumber(['payment', 'reminder'])->name('payments.reminders.attend');
    Route::get('/api/contracts/{contract}/amount', [PaymentController::class, 'getContractAmount'])->whereNumber('contract')->name('api.contracts.amount');
    Route::resource('payments', PaymentController::class)->except('destroy');
});

// ============================================
// INVENTARIO: EQUIPOS, MATERIALES Y KARDEX
// ============================================

Route::middleware(['auth'])->group(function (): void {
    // Gestión de Equipos
    Route::get('/equipments', [EquipmentController::class, 'index'])->name('equipments.index');
    Route::get('/equipments/create', [EquipmentController::class, 'create'])->name('equipments.create');
    Route::post('/equipments', [EquipmentController::class, 'store'])->name('equipments.store');
    Route::get('/equipments/{equipment}', [EquipmentController::class, 'show'])->whereNumber('equipment')->name('equipments.show');
    Route::get('/equipments/{equipment}/edit', [EquipmentController::class, 'edit'])->whereNumber('equipment')->name('equipments.edit');
    Route::put('/equipments/{equipment}', [EquipmentController::class, 'update'])->whereNumber('equipment')->name('equipments.update');
    Route::delete('/equipments/{equipment}', [EquipmentController::class, 'deactivate'])->whereNumber('equipment')->name('equipments.deactivate');
    Route::get('/equipments/{equipment}/kardex', [EquipmentController::class, 'kardex'])->whereNumber('equipment')->name('equipments.kardex');

    // Importación masiva de Equipos
    Route::get('/equipments/import/form', [EquipmentController::class, 'importForm'])->name('equipments.import');
    Route::get('/equipments/import/template', [EquipmentController::class, 'downloadTemplate'])->name('equipments.template');
    Route::post('/equipments/import/process', [EquipmentController::class, 'processImport'])->name('equipments.import.process');
    Route::post('/equipments/import/confirm', [EquipmentController::class, 'confirmImport'])->name('equipments.import.confirm');
    Route::post('/equipments/import/cancel', [EquipmentController::class, 'cancelImport'])->name('equipments.import.cancel');

    // Gestión de Materiales
    Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::get('/materials/create', [MaterialController::class, 'create'])->name('materials.create');
    Route::post('/materials', [MaterialController::class, 'store'])->name('materials.store');
    Route::get('/materials/{material}', [MaterialController::class, 'show'])->whereNumber('material')->name('materials.show');
    Route::get('/materials/{material}/edit', [MaterialController::class, 'edit'])->whereNumber('material')->name('materials.edit');
    Route::put('/materials/{material}', [MaterialController::class, 'update'])->whereNumber('material')->name('materials.update');
    Route::delete('/materials/{material}', [MaterialController::class, 'deactivate'])->whereNumber('material')->name('materials.deactivate');
    Route::get('/materials/{material}/kardex', [MaterialController::class, 'kardex'])->whereNumber('material')->name('materials.kardex');

    // Operaciones de Stock de Materiales
    Route::get('/materials/{material}/entry', [MaterialController::class, 'entryForm'])->whereNumber('material')->name('materials.entry');
    Route::post('/materials/{material}/entry', [MaterialController::class, 'storeEntry'])->whereNumber('material')->name('materials.entry.store');
    Route::get('/materials/{material}/exit', [MaterialController::class, 'exitForm'])->whereNumber('material')->name('materials.exit');
    Route::post('/materials/{material}/exit', [MaterialController::class, 'storeExit'])->whereNumber('material')->name('materials.exit.store');
    Route::get('/materials/{material}/adjustment', [MaterialController::class, 'adjustmentForm'])->whereNumber('material')->name('materials.adjustment');
    Route::post('/materials/{material}/adjustment', [MaterialController::class, 'storeAdjustment'])->whereNumber('material')->name('materials.adjustment.store');

    // Kardex de Inventario (consulta únicamente)
    Route::get('/kardex', [KardexController::class, 'index'])->name('kardex.index');
});

Route::middleware(['auth'])->group(function (): void {
    Route::view('/reports', 'analytics.screen', ['screen' => 'reports'])->name('reports.index');
    // Exportación real pendiente; la entrega actual contiene solo pantallas de demostración.
});

Route::middleware(['auth'])->group(function (): void {
    Route::view('/indicators', 'analytics.screen', ['screen' => 'indicators'])->name('indicators.index');
    Route::view('/bi', 'analytics.screen', ['screen' => 'bi'])->name('bi.index');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/roles', [\App\Http\Controllers\RoleAccessController::class, 'index'])->name('roles.index');
    Route::post('/roles', [\App\Http\Controllers\RoleAccessController::class, 'store'])->name('roles.store');
    Route::put('/roles/{role}', [\App\Http\Controllers\RoleAccessController::class, 'update'])->name('roles.update');
    Route::get('/user-roles', [\App\Http\Controllers\RoleAccessController::class, 'assignments'])->name('user-roles.index');
    Route::put('/user-roles/{user}', [\App\Http\Controllers\RoleAccessController::class, 'assign'])->name('user-roles.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard/gerencia', [DashboardController::class, 'management'])->name('dashboard.management');
    Route::get('/dashboard/call-center', [DashboardController::class, 'office'])->name('dashboard.office');
    Route::get('/executions', [TechnicianController::class, 'index'])->name('executions.index');
    Route::get('/assigned/contracts', [\App\Http\Controllers\ScopedAccessController::class, 'contracts'])->name('contracts.assigned');
    Route::get('/assigned/equipment', [\App\Http\Controllers\ScopedAccessController::class, 'equipment'])->name('equipments.assigned');
    Route::get('/assigned/kardex', [\App\Http\Controllers\ScopedAccessController::class, 'movements'])->name('kardex.related');
    Route::get('/availability/equipment', [\App\Http\Controllers\ScopedAccessController::class, 'equipmentAvailability'])->name('equipments.availability');
    Route::get('/availability/materials', [\App\Http\Controllers\ScopedAccessController::class, 'materialAvailability'])->name('materials.availability');
    Route::get('/orders', [\App\Http\Controllers\InstallationOrderController::class, 'index'])->name('orders.index');
    Route::get('/assigned/orders', [\App\Http\Controllers\InstallationOrderController::class, 'index'])->name('orders.assigned');
    Route::post('/orders', [\App\Http\Controllers\InstallationOrderController::class, 'store'])->name('orders.store');
    Route::patch('/orders/{pedido}/schedule', [\App\Http\Controllers\InstallationOrderController::class, 'schedule'])->whereNumber('pedido')->name('orders.schedule');
    Route::patch('/orders/{pedido}/cancel', [\App\Http\Controllers\InstallationOrderController::class, 'cancel'])->whereNumber('pedido')->name('orders.cancel');
});

Route::post('/sales/contracts/{contract}/confirm', [SaleController::class, 'confirm'])->middleware('auth')->whereNumber('contract')->name('sales.confirm');
