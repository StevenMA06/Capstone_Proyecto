@php
$groups = [
    'General' => ['dashboard' => ['Panel principal', 'home']],
    'Comercial' => ['clients' => ['Clientes', 'users'], 'plans' => ['Planes de servicio', 'file'], 'contracts' => ['Contratos', 'file'], 'sales' => ['Ventas', 'cart'], 'payments' => ['Pagos', 'money'], 'zones' => ['Zonas de cobertura', 'pin']],
    'Operaciones' => ['installations' => ['Instalaciones', 'tool'], 'technician' => ['Panel técnico', 'tool']],
    'Inventario' => ['equipments' => ['Equipos', 'router'], 'materials' => ['Materiales', 'box'], 'kardex' => ['Kardex', 'file']],
    'Administración' => ['employees' => ['Empleados', 'users'], 'users' => ['Usuarios', 'users'], 'roles' => ['Roles y permisos', 'file'], 'user-roles' => ['Usuarios y roles', 'users'], 'access' => ['Historial de accesos', 'file']],
    'Información' => ['reports' => ['Reportes', 'chart'], 'indicators' => ['Indicadores', 'chart'], 'bi' => ['Business Intelligence', 'chart'], 'chatbot' => ['Chatbot', 'chat']],
];
@endphp
@foreach($groups as $group => $items)
    @php($visible = collect($items)->filter(fn ($item, $key) => auth()->user()->hasPermission($key.'.view')))
    @if($visible->isNotEmpty())<p class="nav-section">{{ $group }}</p>@endif
    @foreach($visible as $key => [$label, $icon])
    <a class="{{ request()->routeIs($key, $key.'.*') ? 'nav-active' : '' }}" @if(request()->routeIs($key, $key.'.*')) aria-current="page" @endif href="{{ route($key === 'dashboard' ? 'dashboard' : $key.'.index') }}">@include('layouts.module-icon', ['icon' => $icon])<span>{{ $label }}</span></a>
    @endforeach
@endforeach
