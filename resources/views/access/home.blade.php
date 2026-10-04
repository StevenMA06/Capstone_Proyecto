@extends('layouts.management')
@section('title', 'Inicio')
@section('workspace-content')
<h1>Bienvenido, {{ auth()->user()->name }}</h1>
<p>Selecciona una opción del menú para comenzar. Si no tienes opciones disponibles, solicita a Gerencia que revise tus permisos.</p>
@endsection
