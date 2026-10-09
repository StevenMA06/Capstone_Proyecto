@extends('layouts.management')
@section('title','Panel de Call Center')
@section('workspace-content')
<header class="module-heading"><h1>Panel de Call Center</h1><p>Bienvenido, {{ auth()->user()->name }}. Selecciona una funcionalidad disponible en tu menú.</p></header>
<section class="client-list-panel"><h2>Atención comercial</h2><p>Los accesos y las acciones disponibles corresponden a los permisos de tus roles activos.</p>
@can('office.coordination')<a class="action-link" href="{{ route('office.coordination.index') }}">Coordinar visitas de instalación</a>@endcan
@foreach(['clients'=>'Clientes','contracts'=>'Contratos','sales'=>'Ventas','payments'=>'Pagos'] as $module=>$label)
@can($module.'.view')<a class="action-link secondary" href="{{ route($module.'.index') }}">{{ $label }}</a>@endcan
@endforeach</section>
@endsection
