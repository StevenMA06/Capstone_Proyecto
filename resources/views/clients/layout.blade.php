@extends('layouts.app')
@section('title', 'Gestión de clientes')
@section('content')
<section class="management clients-management">
    <header class="management-header">
        <div><span class="eyebrow">LUNNY’S PERU R.2.D.</span><h1>Gestión de clientes</h1><p>Consulta y administra los datos y el historial de tus clientes.</p></div>
        <a class="secondary" href="{{ route('dashboard') }}">Volver al panel</a>
    </header>
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="notice error-summary" role="alert"><strong>Revisa los datos indicados:</strong>
            <ul>@foreach($errors->messages() as $field => $messages)<li><a href="#{{ $field }}">{{ $messages[0] }}</a></li>@endforeach</ul>
        </div>
    @endif
    @yield('clients-content')
</section>
@endsection
