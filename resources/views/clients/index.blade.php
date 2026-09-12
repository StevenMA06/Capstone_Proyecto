@extends('clients.layout')
@section('clients-content')
<div class="management-toolbar"><h2>Clientes registrados <span class="count">{{ $clients->total() }}</span></h2><a class="action-link" href="{{ route('clients.create') }}">+ Registrar cliente</a></div>
<form class="filters" method="GET" action="{{ route('clients.index') }}">
    <div><label for="search">Nombre o número de documento</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Buscar cliente" maxlength="180"></div>
    <div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos los estados</option><option value="ACTIVO" @selected(request('estado') === 'ACTIVO')>Activo</option><option value="INACTIVO" @selected(request('estado') === 'INACTIVO')>Inactivo</option></select></div>
    <button class="action-link" type="submit">Buscar</button><a class="clear-link" href="{{ route('clients.index') }}">Limpiar</a>
</form>
<div class="table-scroll"><table class="users-table"><caption class="sr-only">Clientes registrados</caption><thead><tr><th>ID</th><th>Cliente</th><th>Documento</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($clients as $client)
<tr>
    <td>#{{ $client->getKey() }}</td>
    <td><a href="{{ route('clients.show', $client) }}">{{ $client->nombres_razon_social }} {{ $client->apellidos }}</a></td>
    <td>{{ $client->tipo_documento }} {{ $client->numero_documento }}</td><td>{{ $client->telefono ?? '—' }}</td>
    <td><span class="state {{ $client->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ $client->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</span></td>
    <td><div class="row-actions"><a href="{{ route('clients.show', $client) }}" aria-label="Ver ficha de {{ $client->nombres_razon_social }}">Ver ficha</a><a href="{{ route('clients.edit', $client) }}" aria-label="Editar {{ $client->nombres_razon_social }}">Editar</a></div></td>
</tr>
@empty<tr><td colspan="6" class="empty-state">No se encontraron clientes con esos criterios.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['records' => $clients, 'label' => 'Paginación de clientes'])
@endsection
