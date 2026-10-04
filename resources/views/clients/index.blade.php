@extends('clients.layout')
@section('clients-content')
<div class="clients-grid">
<aside class="client-filter-panel" aria-labelledby="filter-title">
    <h2 id="filter-title">Filtros y buscador</h2>
    <p class="muted">Encuentra al cliente que necesitas.</p>
    <form class="filters client-filters" method="GET" action="{{ route('clients.index') }}">
        <div><label for="search">Nombre o documento</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Buscar cliente…" maxlength="180"><small>Nombres, apellidos o número de documento.</small></div>
        <fieldset><legend>Estado del cliente</legend>
            @foreach(['' => 'Todos los estados', 'ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'] as $value => $label)
                <label class="state-option"><input type="radio" name="estado" value="{{ $value }}" @checked((request('estado') ?? '') === $value)><span>{{ $label }}</span></label>
            @endforeach
        </fieldset>
        <div class="filter-buttons"><button class="action-link" type="submit">Aplicar filtros</button><a class="clear-link" href="{{ route('clients.index') }}">Limpiar filtros</a></div>
    </form>
</aside>
<section class="client-list-panel" aria-labelledby="list-title">
    <div class="management-toolbar"><div><h2 id="list-title">Listado de clientes</h2><p class="muted">{{ $clients->total() }} {{ $clients->total() === 1 ? 'cliente encontrado' : 'clientes encontrados' }}</p></div>@can('clients.manage')<a class="action-link" href="{{ route('clients.create') }}"><span aria-hidden="true">＋</span> Registrar cliente</a>@endcan</div>
    <div class="table-scroll"><table class="users-table client-table"><caption class="sr-only">Clientes registrados</caption><thead><tr><th>Documento</th><th>Nombre completo</th><th>Teléfono</th><th>Dirección</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($clients as $client)
    <tr>
        <td><strong>{{ $client->numero_documento }}</strong><small class="table-subtitle">{{ $client->tipo_documento }}</small></td>
        <td><a class="client-name" href="{{ route('clients.show', $client) }}">{{ $client->nombres_razon_social }} {{ $client->apellidos }}</a></td>
        <td>{{ $client->telefono ?? '—' }}</td><td>{{ $client->direccion }}</td>
        <td><span class="state {{ $client->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ $client->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</span></td>
        <td><div class="row-actions"><a href="{{ route('clients.show', $client) }}" aria-label="Ver ficha de {{ $client->nombres_razon_social }}">Ver ficha</a>@can('clients.manage')<a href="{{ route('clients.edit', $client) }}" aria-label="Editar {{ $client->nombres_razon_social }}">Editar</a>@endcan</div></td>
    </tr>
    @empty<tr><td colspan="6" class="empty-state">No se encontraron clientes con esos criterios.</td></tr>@endforelse
    </tbody></table></div>
    @include('clients.pagination', ['records' => $clients, 'label' => 'Paginación de clientes'])
</section>
</div>
@endsection
