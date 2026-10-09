@php
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
        ['Ventas','cart',['sales.view'=>'sales.index']],
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
@endphp
@foreach($groups as $group=>$items)
    @php($links = [])
    @foreach($items as $item)
        @php([$label,$icon,$options] = $item)
        @php($params = $item[3] ?? [])
        @continue($label === 'Ejecución de instalaciones' && auth()->user()->hasPermission('technician.panel'))
        @php($destination = collect($options)->first(fn ($route,$permission)=>auth()->user()->hasPermission($permission)))
        @if($destination) @php($links[]=[$label,$icon,$destination,$params]) @endif
    @endforeach
    @if(count($links))<p class="nav-section">{{ $group }}</p>@endif
    @foreach($links as [$label,$icon,$destination,$params])
        @php($active = request()->routeIs($destination) && (count($params) ? collect($params)->every(fn ($value,$key) => request($key) === $value) : true))
        <a class="{{ $active ? 'nav-active' : '' }}" @if($active) aria-current="page" @endif href="{{ route($destination, $params) }}">@include('layouts.module-icon',['icon'=>$icon])<span>{{ $label }}</span></a>
    @endforeach
@endforeach
