@extends('clients.layout')
@section('clients-content')
<div class="management-toolbar">
    <div><h2>Cliente #{{ $client->getKey() }} · {{ $client->nombres_razon_social }} {{ $client->apellidos }}</h2>
        <p>Estado: <span class="state {{ $client->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ $client->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</span></p>
    </div>
    <div class="row-actions"><a class="secondary" href="{{ route('clients.index') }}">Ver clientes</a>@can('clients.manage')<a class="action-link" href="{{ route('clients.edit', $client) }}">Editar cliente</a>@endcan</div>
</div>
<dl class="account-detail">
    <div><dt>Documento</dt><dd>{{ $client->tipo_documento }} {{ $client->numero_documento }}</dd></div>
    <div><dt>Dirección</dt><dd>{{ $client->direccion }}</dd></div>
    <div><dt>Correo electrónico</dt><dd>{{ $client->correo ?? '—' }}</dd></div>
    <div><dt>Teléfono</dt><dd>{{ $client->telefono ?? '—' }}</dd></div>
    <div><dt>Referencia de dirección</dt><dd>{{ $client->referencia_direccion ?? '—' }}</dd></div>
    <div><dt>Distrito / Provincia / Departamento</dt><dd>{{ $client->distrito ?? '—' }} / {{ $client->provincia ?? '—' }} / {{ $client->departamento ?? '—' }}</dd></div>
    <div><dt>Latitud / Longitud</dt><dd>{{ $client->latitud ?? '—' }} / {{ $client->longitud ?? '—' }}</dd></div>
    <div><dt>Registrado / Última actualización</dt><dd>{{ $client->creado_en?->format('d/m/Y H:i') }} / {{ $client->actualizado_en?->format('d/m/Y H:i') }}</dd></div>
</dl>
@if($client->estado === 'ACTIVO')
<details class="client-deactivate">
    <summary>Inhabilitar cliente</summary>
    <p class="muted">El cliente pasará a inactivo. Sus datos, contratos, pagos e instalaciones seguirán disponibles para consulta.</p>
    @can('clients.manage')<form method="POST" action="{{ route('clients.deactivate', $client) }}">@csrf @method('PATCH')<button class="secondary danger" type="submit">Confirmar inhabilitación</button></form>@endcan
</details>
@else
<p class="notice success">Este cliente está inactivo. Sus datos e historiales permanecen disponibles.</p>
@endif
<section aria-labelledby="history-title">
    <h2 id="history-title">Historial del cliente</h2>
    <nav class="client-tabs" aria-label="Historial del cliente">
        @foreach(['contratos' => 'Contratos', 'pagos' => 'Pagos', 'instalaciones' => 'Instalaciones'] as $key => $label)
            <a class="{{ $tab === $key ? 'action-link' : 'secondary' }}" href="{{ route('clients.show', ['client' => $client, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @include('clients.history-'.$tab)
    @include('clients.pagination', ['records' => $history, 'label' => 'Paginación de '.$tab])
</section>
@endsection
