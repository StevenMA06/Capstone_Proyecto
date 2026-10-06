@php
$groups = [
    'Paneles' => [
        ['Panel de Gerencia','home',['dashboard.view'=>'dashboard.management']],
        ['Panel de Call Center','home',['office.view'=>'dashboard.office']],
        ['Panel técnico','tool',['technician.panel'=>'technician.index']],
    ],
    'Comercial' => [
        ['Clientes','users',['clients.view'=>'clients.index']],
        ['Planes de servicio','file',['plans.view'=>'plans.index']],
        ['Contratos','file',['contracts.view'=>'contracts.index','contracts.assigned'=>'contracts.assigned']],
        ['Ventas','cart',['sales.view'=>'sales.index']],
        ['Pagos','money',['payments.view'=>'payments.index']],
        ['Zonas de cobertura','pin',['zones.view'=>'zones.index']],
    ],
    'Operaciones' => [
        ['Pedidos','tool',['orders.view'=>'orders.index','orders.assigned'=>'orders.assigned']],
        ['Ejecución de instalaciones','tool',['technician.view'=>'executions.index']],
    ],
    'Inventario' => [
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
    @foreach($items as [$label,$icon,$options])
        @continue($label === 'Ejecución de instalaciones' && auth()->user()->hasPermission('technician.panel'))
        @php($destination = collect($options)->first(fn ($route,$permission)=>auth()->user()->hasPermission($permission)))
        @if($destination) @php($links[]=[$label,$icon,$destination]) @endif
    @endforeach
    @if(count($links))<p class="nav-section">{{ $group }}</p>@endif
    @foreach($links as [$label,$icon,$destination])
        <a class="{{ request()->routeIs($destination) ? 'nav-active' : '' }}" @if(request()->routeIs($destination)) aria-current="page" @endif href="{{ route($destination) }}">@include('layouts.module-icon',['icon'=>$icon])<span>{{ $label }}</span></a>
    @endforeach
@endforeach
