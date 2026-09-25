@extends('equipments.layout')
@section('equipments-content')
<div class="client-detail">
    <div class="detail-header">
        <div><h2>{{ $equipment->nombre }}</h2><p class="muted">Código: {{ $equipment->codigo }} @if($equipment->numero_serie) | Serie: {{ $equipment->numero_serie }}@endif</p></div>
        <div class="detail-actions"><a class="clear-link" href="{{ route('equipments.index') }}">Volver al listado</a>
            @if($equipment->estado !== 'ASIGNADO')<a href="{{ route('equipments.edit', $equipment) }}" class="action-link">Editar</a>@endif
            @if($equipment->estado !== 'INACTIVO' && $equipment->estado !== 'ASIGNADO')<form method="POST" action="{{ route('equipments.deactivate', $equipment) }}" style="display:inline;" onsubmit="return confirm('¿Inhabilitar este equipo?');"> @csrf @method('DELETE') <button type="submit" class="clear-link" style="cursor:pointer;">Inhabilitar</button></form>@endif
            <a href="{{ route('equipments.kardex', $equipment) }}" class="action-link">Ver Kardex</a>
        </div>
    </div>
    
    <div class="detail-grid">
        <div class="detail-card"><h3>Información del equipo</h3><dl><dt>Tipo</dt><dd>{{ $equipment->tipo }}</dd><dt>Marca</dt><dd>{{ $equipment->marca ?? '—' }}</dd><dt>Modelo</dt><dd>{{ $equipment->modelo ?? '—' }}</dd><dt>Condición</dt><dd><span class="state {{ in_array($equipment->condicion, ['NUEVO', 'BUENO']) ? 'active' : (in_array($equipment->condicion, ['MALO', 'MALOGRADO']) ? 'inactive' : '') }}">{{ ucfirst(strtolower($equipment->condicion)) }}</span></dd><dt>Estado</dt><dd><span class="state {{ $equipment->estado === 'DISPONIBLE' ? 'active' : ($equipment->estado === 'INACTIVO' ? 'inactive' : '') }}">{{ ucfirst(strtolower($equipment->estado)) }}</span></dd>@if($equipment->observaciones)<dt>Observaciones</dt><dd>{{ $equipment->observaciones }}</dd>@endif</dl></div>
        
        <div class="detail-card"><h3>Registro</h3><dl><dt>Registrado por</dt><dd>{{ $equipment->creator->nombre_completo ?? 'Usuario eliminado' }}</dd><dt>Fecha de registro</dt><dd>{{ $equipment->creado_en->format('d/m/Y H:i') }}</dd><dt>Última actualización</dt><dd>{{ $equipment->actualizado_en->format('d/m/Y H:i') }}</dd></dl></div>
    </div>
    
    <div class="detail-card"><h3>Historial de movimientos Kardex ({{ $movements->total() }})</h3>
        @if($movements->count() > 0)
        <div class="table-scroll"><table class="users-table"><thead><tr><th>Fecha</th><th>Movimiento</th><th>Cantidad</th><th>Responsable</th><th>Observación</th></tr></thead><tbody>
        @foreach($movements as $movement)<tr><td>{{ $movement->fecha_movimiento->format('d/m/Y H:i') }}</td><td><strong>{{ $movement->tipo_movimiento }}</strong></td><td>{{ \App\Support\InventoryQuantity::format($movement->cantidad) }}</td><td>{{ $movement->responsible->nombre_completo ?? '—' }}</td><td>{{ $movement->observacion ?? '—' }}</td></tr>@endforeach
        </tbody></table></div>
        @if($movements->hasPages()) @include('clients.pagination', ['records' => $movements, 'label' => 'Paginación de movimientos']) @endif
        @else
        <p class="muted">No hay movimientos registrados para este equipo.</p>
        @endif
    </div>
</div>
@endsection
