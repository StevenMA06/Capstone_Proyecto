@extends('layouts.app')
@section('title', 'Mi panel')
@section('content')
<section class="dashboard card">
    <span class="eyebrow">ESTÁS EN TU ESPACIO</span>
    <h1>Hola, {{ auth()->user()->name }}.</h1>
    <p class="muted">Tu sesión está activa. Este es el punto de partida de tu aplicación.</p>
    <div class="account"><span class="avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div><strong>{{ auth()->user()->name }}</strong><p>{{ auth()->user()->correo }}</p></div><span class="badge">Conectado</span></div>
    <p class="muted">Rol: {{ auth()->user()->role->nombre }} · Usuario: {{ auth()->user()->nombre_usuario }}</p>
    @can('manage-users')<p><a class="action-link" href="{{ route('users.index') }}">Gestionar usuarios</a></p>@endcan
    @can('register-clients')<p><a class="action-link" href="{{ route('clients.index') }}">Gestionar clientes</a> <a class="secondary" href="{{ route('clients.create') }}">Registrar cliente</a></p>@endcan
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="secondary">Cerrar sesión <span aria-hidden="true">↗</span></button></form>
</section>
@endsection
