@extends('layouts.management')
@section('title', 'Historial de accesos')
@section('workspace-content')
<section class="management clients-management">
    <header class="module-heading"><span class="eyebrow">GERENCIA · CONTROL DE ACCESO</span><h1>Historial de accesos</h1><p>Consulta quién intentó ingresar al sistema, cuándo y con qué resultado.</p></header>
    @if($errors->any())<div class="notice error-summary" role="alert">{{ $errors->first() }}</div>@endif
    <form class="filters" method="GET" action="{{ route('access.index') }}">
        <div><label for="search">Nombre de usuario</label><input id="search" name="search" value="{{ request('search') }}" maxlength="60"></div>
        <div><label for="date">Fecha</label><input id="date" name="date" type="date" value="{{ request('date') }}"></div>
        <div><label for="result">Resultado</label><select id="result" name="result"><option value="">Todos</option><option value="EXITOSO" @selected(request('result') === 'EXITOSO')>Exitosos</option><option value="RECHAZADO" @selected(request('result') === 'RECHAZADO')>Rechazados</option></select></div>
        <button class="action-link" type="submit">Buscar accesos</button><a class="clear-link" href="{{ route('access.index') }}">Limpiar filtros</a>
    </form>
    <div class="client-list-panel"><div class="table-scroll"><table class="users-table"><caption class="sr-only">Accesos registrados</caption><thead><tr><th>Usuario</th><th>Fecha</th><th>Hora</th><th>Resultado</th></tr></thead><tbody>
    @forelse($records as $record)
        <tr><td>{{ $record->nombre_usuario ?? $record->nombre_usuario_intentado ?? 'Desconocido' }}</td>
        <td>{{ \Carbon\Carbon::parse($record->fecha_hora)->format('d/m/Y') }}</td><td>{{ \Carbon\Carbon::parse($record->fecha_hora)->format('H:i:s') }}</td>
        <td><span class="state {{ $record->resultado === 'EXITOSO' ? 'active' : 'inactive' }}">{{ ['EXITOSO' => 'Exitoso', 'CREDENCIALES_INCORRECTAS' => 'Credenciales incorrectas', '2FA_INCORRECTO' => 'Código incorrecto', '2FA_VENCIDO' => 'Código vencido', 'CUENTA_INACTIVA' => 'Cuenta no disponible', 'BLOQUEADO' => 'Bloqueado'][$record->resultado] ?? 'Rechazado' }}</span></td></tr>
    @empty<tr><td colspan="4" class="empty-state">No se encontraron accesos con esos filtros.</td></tr>@endforelse
    </tbody></table></div>
    @include('clients.pagination', ['records' => $records, 'label' => 'Paginación de accesos'])
    </div>
</section>
@endsection
