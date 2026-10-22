<?php
$groups = [
    'Paneles' => [
        ['Panel de Gerencia','home',['dashboard.view'=>'dashboard.management']],
        ['Panel de Call Center','home',['office.view'=>'dashboard.office']],
        [auth()->user()->hasPermission('technician.supervise') ? 'Supervisión de instalaciones' : 'Mis instalaciones asignadas','tool',['technician.panel'=>'technician.index']],
    ],
    'Comercial' => [
        ['Clientes','users',['clients.view'=>'clients.index']],
        ['Planes de servicio','file',['plans.view'=>'plans.index']],
        ['Contratos','file',['contracts.view'=>'contracts.index','contracts.assigned'=>'contracts.assigned']],
        ['Solicitudes de cancelación','file',['contracts.cancel.inbox'=>'contracts.cancellations']],
        // DESHABILITADO TEMPORALMENTE - Ventas
        // ['Ventas','cart',['sales.view'=>'sales.index']],
        ['Pagos','money',['payments.view'=>'payments.index']],
        ['Zonas de cobertura','pin',['zones.view'=>'zones.index']],
        ['Coordinación de visitas','tool',['office.coordination'=>'office.coordination.index']],
    ],
    'Operaciones' => [
        ['Pedidos de instalación','tool',['orders.view'=>'orders.index','orders.assigned'=>'orders.assigned']],
        ['Ejecución de instalaciones','tool',['technician.view'=>'executions.index']],
    ],
    'Inventario' => [
        ['Solicitudes de instalación','box',['warehouse.view'=>'warehouse.requests.index']],
        ['Equipos','router',['equipments.view'=>'equipments.index','equipments.availability'=>'equipments.availability','equipments.assigned'=>'equipments.assigned']],
        ['Materiales','box',['materials.view'=>'materials.index','materials.availability'=>'materials.availability']],
        ['Kardex','file',['kardex.view'=>'kardex.index','kardex.related'=>'kardex.related']],
    ],
    'Administración' => [
        ['Empleados','users',['employees.view'=>'employees.index']],
        ['Usuarios','users',['users.view'=>'users.index']],
        ['Roles y permisos','file',['roles.view'=>'roles.index']],
        ['Asignación de roles','users',['user-roles.view'=>'user-roles.index']],
        ['Historial de accesos','file',['access.view'=>'access.index']],
    ],
    'Información' => [
        ['Reportes','chart',['reports.view'=>'reports.index']],
        ['Indicadores','chart',['indicators.view'=>'indicators.index']],
        ['Business Intelligence','chart',['bi.view'=>'bi.index']],
        ['Chatbot','chat',['chatbot.view'=>'chatbot.index']],
    ],
];
$groupIcons = ['Paneles' => 'home', 'Comercial' => 'cart', 'Operaciones' => 'tool', 'Inventario' => 'box', 'Administración' => 'users', 'Información' => 'chart'];
?>
<?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group=>$items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php ($links = []); ?>
    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ([$label,$icon,$options] = $item); ?>
        <?php ($params = $item[3] ?? []); ?>
        <?php if($label === 'Ejecución de instalaciones' && auth()->user()->hasPermission('technician.panel')) continue; ?>
        <?php ($destination = collect($options)->first(fn ($route,$permission)=>auth()->user()->hasPermission($permission))); ?>
        <?php if($destination): ?> <?php ($links[]=[$label,$icon,$destination,$params]); ?> <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if(count($links)): ?>
        <?php ($groupActive = collect($links)->contains(fn ($link) => request()->routeIs($link[2]) || request()->routeIs(explode('.', $link[2])[0].'.*'))); ?>
        <details class="nav-group" <?php if($groupActive): ?> open <?php endif; ?>>
            <summary class="nav-section"><span class="nav-group-label"><span class="nav-group-icon"><?php echo $__env->make('layouts.module-icon', ['icon' => $groupIcons[$group]], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span><span><?php echo e($group); ?></span></span><span class="nav-chevron" aria-hidden="true"></span></summary>
            <div class="nav-group-links">
                <?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label,$icon,$destination,$params]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php ($active = request()->routeIs($destination) && (count($params) ? collect($params)->every(fn ($value,$key) => request($key) === $value) : true)); ?>
                    <a class="<?php echo e($active ? 'nav-active' : ''); ?>" <?php if($active): ?> aria-current="page" <?php endif; ?> href="<?php echo e(route($destination, $params)); ?>"><?php echo $__env->make('layouts.module-icon',['icon'=>$icon], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><span><?php echo e($label); ?></span></a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </details>
    <?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/access/navigation.blade.php ENDPATH**/ ?>